<?php


use Illuminate\Support\Facades\Schedule;



Schedule::command('presence:sweep')
    ->everyThirtySeconds()
    ->withoutOverlapping();