<?php

class Logs extends Eloquent{

	protected $fillable = array('book_issue_id', 'student_id', 'issue_by', 'issued_at', 'return_time');

    public $timestamps = false;

	protected $table = 'book_issue_log';
	protected $primaryKey = 'id';

	protected $hidden = array();

	/**
	 * Relationships
	 */
	public function issue() {
	    return $this->belongsTo('Issue', 'book_issue_id', 'issue_id');
	}

	public function student() {
	    return $this->belongsTo('Student', 'student_id', 'student_id');
	}

	public function issuedBy() {
	    return $this->belongsTo('User', 'issue_by', 'id');
	}

	/**
	 * Helper methods
	 */
	public function isReturned() {
	    return $this->return_time > 0;
	}

	public function isActive() {
	    return $this->return_time == 0;
	}

	public function getDaysIssued() {
	    if ($this->isActive()) {
	        return ceil((time() - $this->issued_at) / 86400);
	    }
	    return ceil(($this->return_time - $this->issued_at) / 86400);
	}

	public function isOverdue($dueDays = 14) {
	    if ($this->isActive()) {
	        $daysSinceIssued = $this->getDaysIssued();
	        return $daysSinceIssued > $dueDays;
	    }
	    return false;
	}

	/**
	 * Scopes
	 */
	public function scopeActive($query) {
	    return $query->where('return_time', 0);
	}

	public function scopeReturned($query) {
	    return $query->where('return_time', '>', 0);
	}

	public function scopeOverdue($query, $dueDays = 14) {
	    $overdueTimestamp = time() - ($dueDays * 86400);
	    return $query->where('return_time', 0)
	                 ->where('issued_at', '<', $overdueTimestamp);
	}
}
