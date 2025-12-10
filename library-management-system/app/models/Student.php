<?php

class Student extends Eloquent{

	protected $fillable = array('first_name','last_name','approved','rejected','category','roll_num','branch','year','email_id','books_issued');

    public $timestamps = false;

	protected $table = 'students';
	protected $primaryKey = 'student_id';

	protected $hidden = array();

	/**
	 * Relationships
	 */
	public function category() {
	    return $this->belongsTo('StudentCategories', 'category', 'cat_id');
	}

	public function branch() {
	    return $this->belongsTo('Branch', 'branch', 'id');
	}

	public function issueLogs() {
	    return $this->hasMany('Logs', 'student_id', 'student_id');
	}

	public function activeIssues() {
	    return $this->issueLogs()->where('return_time', 0);
	}

	public function ratings() {
	    return $this->hasMany('BookRating', 'student_id', 'student_id');
	}

	/**
	 * Helper methods
	 */
	public function canBorrowMore() {
	    $maxAllowed = $this->category ? $this->category->max_allowed : 0;
	    return $this->books_issued < $maxAllowed;
	}

	public function getStatus() {
	    if ($this->approved) return 'approved';
	    if ($this->rejected) return 'rejected';
	    return 'pending';
	}

	public function isApproved() {
	    return $this->approved == 1;
	}

	public function isPending() {
	    return $this->approved == 0 && $this->rejected == 0;
	}

	/**
	 * Scopes
	 */
	public function scopeApproved($query) {
	    return $query->where('approved', 1);
	}

	public function scopePending($query) {
	    return $query->where('approved', 0)->where('rejected', 0);
	}

	public function scopeRejected($query) {
	    return $query->where('rejected', 1);
	}
}
