<?php

use Tests\TestCase;

pest()->extend(TestCase::class)->in('Browser');
pest()->browser()->timeout(20000);
