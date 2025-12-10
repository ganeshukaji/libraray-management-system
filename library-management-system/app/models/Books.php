<?php

class Books extends Eloquent{

	protected $fillable = array('book_id', 'title', 'author', 'category_id', 'description', 'added_by');

    public $timestamps = false;

	protected $table = 'books';
	protected $primaryKey = 'book_id';

	protected $hidden = array();

	/**
	 * Relationships
	 */
	public function category() {
	    return $this->belongsTo('Categories', 'category_id', 'id');
	}

	public function issues() {
	    return $this->hasMany('Issue', 'book_id', 'book_id');
	}

	public function addedBy() {
	    return $this->belongsTo('User', 'added_by', 'id');
	}

	public function ratings() {
	    return $this->hasMany('BookRating', 'book_id', 'book_id');
	}

	/**
	 * Helper methods
	 */
	public function availableCopies() {
	    return $this->issues()->where('available_status', 1)->count();
	}

	public function totalCopies() {
	    return $this->issues()->count();
	}

	public function isAvailable() {
	    return $this->availableCopies() > 0;
	}

	/**
	 * Scopes
	 */
	public function scopeAvailable($query) {
	    return $query->whereHas('issues', function($q) {
	        $q->where('available_status', 1);
	    });
	}

	public function scopeByCategory($query, $categoryId) {
	    return $query->where('category_id', $categoryId);
	}
}
