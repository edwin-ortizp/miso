<?php

namespace App\Exceptions;

/** Internal signal to roll back a cancelled Eloquent insertion. */
class TaskCreationCancelled extends \RuntimeException {}
