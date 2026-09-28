<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
class Customer extends Authenticatable
{
    protected $fillable = ['phone', 'password'];
    protected $hidden = ['password', 'remember_token'];
    protected function casts(): array { return ['password' => 'hashed']; }
    public function orders() { return $this->hasMany(Order::class); }
}
