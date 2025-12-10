<?php

namespace App\Services;

use Books;
use Student;
use Logs;
use DB;

class RecommendationService
{
    /**
     * Get book recommendations for a student
     *
     * @param int $studentId
     * @param int $limit
     * @return \Illuminate\Support\Collection
     */
    public function getRecommendationsForStudent($studentId, $limit = 10)
    {
        $student = Student::find($studentId);

        if (!$student) {
            return collect([]);
        }

        // Get student's reading history
        $readBooks = $this->getStudentReadBooks($studentId);

        if ($readBooks->isEmpty()) {
            // New user - return popular books in their category
            return $this->getPopularBooksInCategory($student->category, $limit);
        }

        // Hybrid approach
        $collaborativeScores = $this->collaborativeFiltering($studentId, $readBooks);
        $contentBasedScores = $this->contentBasedFiltering($readBooks);

        // Combine scores (70% collaborative, 30% content-based)
        $combinedScores = $this->combineScores(
            $collaborativeScores,
            $contentBasedScores,
            0.7,
            0.3
        );

        // Get top N recommendations
        return $this->fetchRecommendedBooks($combinedScores, $readBooks, $limit);
    }

    /**
     * Get similar books for a given book
     *
     * @param int $bookId
     * @param int $limit
     * @return \Illuminate\Support\Collection
     */
    public function getSimilarBooks($bookId, $limit = 5)
    {
        $book = Books::with('category')->find($bookId);

        if (!$book) {
            return collect([]);
        }

        // Find books in same category by same author
        $similarBooks = Books::with(['category', 'issues'])
            ->where('book_id', '!=', $bookId)
            ->where(function($query) use ($book) {
                $query->where('category_id', $book->category_id)
                      ->orWhere('author', $book->author);
            })
            ->limit($limit * 2)
            ->get();

        // Score based on matches
        $scored = $similarBooks->map(function($similar) use ($book) {
            $score = 0;
            if ($similar->category_id == $book->category_id) $score += 0.6;
            if ($similar->author == $book->author) $score += 0.4;

            return [
                'book' => $similar,
                'score' => $score
            ];
        })->sortByDesc('score')->take($limit);

        return $scored->pluck('book');
    }

    /**
     * Collaborative filtering based on similar students
     */
    private function collaborativeFiltering($studentId, $readBooks)
    {
        // Find students with similar reading patterns
        $similarStudents = DB::table('book_issue_log as log1')
            ->select('log1.student_id', DB::raw('COUNT(DISTINCT log1.book_issue_id) as common_books'))
            ->join('book_issue_log as log2', function($join) use ($studentId) {
                $join->on('log1.book_issue_id', '=', 'log2.book_issue_id')
                     ->where('log2.student_id', '=', $studentId);
            })
            ->join('book_issue as issue', 'log1.book_issue_id', '=', 'issue.issue_id')
            ->whereIn('issue.book_id', $readBooks->pluck('book_id')->toArray())
            ->where('log1.student_id', '!=', $studentId)
            ->groupBy('log1.student_id')
            ->having('common_books', '>=', 2)
            ->orderByDesc('common_books')
            ->limit(20)
            ->get();

        if ($similarStudents->isEmpty()) {
            return [];
        }

        // Get books borrowed by similar students
        $recommendedBooks = DB::table('book_issue_log')
            ->select('books.book_id', DB::raw('COUNT(DISTINCT book_issue_log.student_id) as frequency'))
            ->join('book_issue', 'book_issue_log.book_issue_id', '=', 'book_issue.issue_id')
            ->join('books', 'book_issue.book_id', '=', 'books.book_id')
            ->whereIn('book_issue_log.student_id', $similarStudents->pluck('student_id')->toArray())
            ->whereNotIn('books.book_id', $readBooks->pluck('book_id')->toArray())
            ->groupBy('books.book_id')
            ->orderByDesc('frequency')
            ->get();

        // Calculate scores
        $scores = [];
        $maxFrequency = $recommendedBooks->max('frequency') ?: 1;

        foreach ($recommendedBooks as $book) {
            $scores[$book->book_id] = $book->frequency / $maxFrequency;
        }

        return $scores;
    }

    /**
     * Content-based filtering based on book attributes
     */
    private function contentBasedFiltering($readBooks)
    {
        $scores = [];

        // Get category distribution
        $categoryPreferences = $readBooks->groupBy('category_id')
            ->map(function($books) {
                return count($books);
            })
            ->sortDesc();

        $totalBooks = $readBooks->count();

        // Get author distribution
        $authorPreferences = $readBooks->groupBy('author')
            ->map(function($books) {
                return count($books);
            })
            ->sortDesc();

        // Find similar books
        $similarBooks = Books::query()
            ->whereNotIn('book_id', $readBooks->pluck('book_id')->toArray())
            ->where(function($query) use ($categoryPreferences, $authorPreferences) {
                $query->whereIn('category_id', $categoryPreferences->keys()->toArray())
                      ->orWhereIn('author', $authorPreferences->keys()->toArray());
            })
            ->get();

        foreach ($similarBooks as $book) {
            $score = 0;

            // Category similarity (weight: 0.6)
            if ($categoryPreferences->has($book->category_id)) {
                $score += 0.6 * ($categoryPreferences[$book->category_id] / $totalBooks);
            }

            // Author similarity (weight: 0.4)
            if ($authorPreferences->has($book->author)) {
                $score += 0.4 * ($authorPreferences[$book->author] / $totalBooks);
            }

            if ($score > 0) {
                $scores[$book->book_id] = $score;
            }
        }

        return $scores;
    }

    /**
     * Combine scores from different algorithms
     */
    private function combineScores($scores1, $scores2, $weight1, $weight2)
    {
        $combined = [];

        $allBookIds = array_unique(array_merge(
            array_keys($scores1),
            array_keys($scores2)
        ));

        foreach ($allBookIds as $bookId) {
            $score1 = isset($scores1[$bookId]) ? $scores1[$bookId] : 0;
            $score2 = isset($scores2[$bookId]) ? $scores2[$bookId] : 0;
            $combined[$bookId] = ($score1 * $weight1) + ($score2 * $weight2);
        }

        arsort($combined);
        return $combined;
    }

    /**
     * Get popular books in a category for cold start
     */
    private function getPopularBooksInCategory($categoryId, $limit)
    {
        return Books::query()
            ->select('books.*', DB::raw('COUNT(book_issue_log.id) as borrow_count'))
            ->leftJoin('book_issue', 'books.book_id', '=', 'book_issue.book_id')
            ->leftJoin('book_issue_log', 'book_issue.issue_id', '=', 'book_issue_log.book_issue_id')
            ->where('books.category_id', $categoryId)
            ->with(['category', 'issues'])
            ->groupBy('books.book_id')
            ->orderByDesc('borrow_count')
            ->limit($limit)
            ->get();
    }

    private function getStudentReadBooks($studentId)
    {
        return Books::query()
            ->join('book_issue', 'books.book_id', '=', 'book_issue.book_id')
            ->join('book_issue_log', 'book_issue.issue_id', '=', 'book_issue_log.book_issue_id')
            ->where('book_issue_log.student_id', $studentId)
            ->select('books.*')
            ->distinct()
            ->get();
    }

    private function fetchRecommendedBooks($scores, $readBooks, $limit)
    {
        if (empty($scores)) {
            return collect([]);
        }

        $topBookIds = array_slice(array_keys($scores), 0, $limit, true);

        $books = Books::with(['category', 'issues'])
            ->whereIn('book_id', $topBookIds)
            ->get();

        // Sort by scores
        return $books->sortBy(function($book) use ($scores) {
            return -$scores[$book->book_id];
        })->values();
    }
}
