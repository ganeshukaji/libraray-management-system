<?php

class Issue extends Eloquent{

	protected $fillable = array('added_by', 'available_status', 'book_id');

    public $timestamps = false;

	protected $table = 'book_issue';
	protected $primaryKey = 'issue_id';

	protected $hidden = array();

	/**
	 * Relationships
	 */
	public function book() {
	    return $this->belongsTo('Books', 'book_id', 'book_id');
	}

	public function addedBy() {
	    return $this->belongsTo('User', 'added_by', 'id');
	}

	public function logs() {
	    return $this->hasMany('Logs', 'book_issue_id', 'issue_id');
	}

	public function currentLog() {
	    return $this->hasOne('Logs', 'book_issue_id', 'issue_id')
	                ->where('return_time', 0);
	}

	/**
	 * Helper methods
	 */
	public function isAvailable() {
	    return $this->available_status == 1;
	}

	public function isIssued() {
	    return $this->available_status == 0;
	}

	/**
	 * Scopes
	 */
	public function scopeAvailable($query) {
	    return $query->where('available_status', 1);
	}

	public function scopeIssued($query) {
	    return $query->where('available_status', 0);
	}
}
