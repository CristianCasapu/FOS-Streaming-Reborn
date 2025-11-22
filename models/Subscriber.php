<?php
class Subscriber extends FosStreaming {

    protected $table = 'users';

    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }

    public function getCategoryNamesAttribute()
    {
        $return = "";
        $prefix = '';
        foreach($this->categories as $category)
        {
            $return .= $prefix . ' ' . $category->name . '';
            $prefix = ', ';
        }

        return $return;
    }

    public function activities()
    {
        return $this->hasMany(Activity::class, 'user_id');
    }

    public function activity()
    {
        return $this->hasMany(Activity::class, 'user_id');
    }

    public function laststream()
    {
        return $this->hasOne(Stream::class, 'id', 'last_stream');
    }
}
