<?php

class BookRating extends Eloquent{

	protected $fillable = array('student_id', 'book_id', 'rating', 'implicit_positive');

    public $timestamps = true;

	protected $table = 'book_ratings';
	protected $primaryKey = 'id';

	protected $hidden = array();

	/**
	 * Relationships
	 */
	public function student() {
	    return $this->belongsTo('Student', 'student_id', 'student_id');
	}

	public function book() {
	    return $this->belongsTo('Books', 'book_id', 'book_id');
	}
}
