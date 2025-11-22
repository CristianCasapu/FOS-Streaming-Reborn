<?php
class Activity extends FosStreaming {

    protected $table = 'activity';

    public function user()
    {
        return $this->hasOne(Subscriber::class , 'id', 'user_id');
    }

    public function subscriber()
    {
        return $this->hasOne(Subscriber::class , 'id', 'user_id');
    }

    public function stream()
    {
        return $this->hasOne(Stream::class , 'id', 'stream_id');
    }
}
