<?php
declare(strict_types=1);
namespace App\Models;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
class ClassBookList extends Model { use HasPublicId; protected $fillable=['academic_year_id','grade_id','section_id','name','is_active']; protected function casts():array{return ['is_active'=>'boolean'];} public function items(){return $this->hasMany(ClassBookListItem::class);} public function grade(){return $this->belongsTo(Grade::class);} public function section(){return $this->belongsTo(Section::class);} public function academicYear(){return $this->belongsTo(AcademicYear::class);} }
