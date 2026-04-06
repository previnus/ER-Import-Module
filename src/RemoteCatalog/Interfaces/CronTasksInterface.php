<?php

namespace CSI\RemoteCatalog\Interfaces;

interface CronTasksInterface
{
    public function runDaily();
    public function runMonthly();
    public function runWeekly();
    public function runHourly();
    public function isActive();
}