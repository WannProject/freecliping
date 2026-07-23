<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('cache:clear')->daily();

Schedule::command('clips:prune')->hourly()->withoutOverlapping();
