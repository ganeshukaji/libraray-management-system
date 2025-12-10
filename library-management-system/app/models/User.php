<?php

use Illuminate\Auth\UserTrait;
use Illuminate\Auth\UserInterface;
use Illuminate\Auth\Reminders\RemindableTrait;
use Illuminate\Auth\Reminders\RemindableInterface;
use Laravel\Sanctum\HasApiTokens;

class User extends Eloquent implements UserInterface, RemindableInterface {

	/* Alowing Eloquent to insert data into our database */
	protected $fillable = array('name', 'username', 'password', 'verification_status', 'role');

        public $timestamps = false;

	use UserTrait, RemindableTrait, HasApiTokens;

	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'users';
	protected $primaryKey = 'id';

	/**
	 * The attributes excluded from the model's JSON form.
	 *
	 * @var array
	 */
	protected $hidden = array('password', 'remember_token');

	public function getAuthPassword() {
	    return $this->password;
	}

	/**
	 * Relationships
	 */
	public function addedBooks() {
	    return $this->hasMany('Books', 'added_by', 'id');
	}

	public function addedIssues() {
	    return $this->hasMany('Issue', 'added_by', 'id');
	}

	public function issuedLogs() {
	    return $this->hasMany('IssueLog', 'issue_by', 'id');
	}

	/**
	 * Helper methods
	 */
	public function isAdmin() {
	    return in_array($this->role, ['admin', 'super_admin']);
	}

	public function isSuperAdmin() {
	    return $this->role === 'super_admin';
	}

}
