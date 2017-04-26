<?php
function month_prev($period)
{
    $dt = date_create_from_format("Y-m", substr($period, 0, 7));
    $dt->sub(new DateInterval("P1M"));
    return date_format($dt, "Y-m");
}
function month_next($period)
{
    $dt = date_create_from_format("Y-m", substr($period, 0, 7));
    $dt->add(new DateInterval("P1M"));
    return date_format($dt, "Y-m");
}

/**
 * Returns start date of month in ISO formatted string
 *
 * @param string $period Month in "Y-m" format or date in "Y-m-d" format
 * @return string ISO date
 */
function month_start_date($period)
{
    $dt = date_create_from_format("Y-m", substr($period, 0, 7));

    $dt->modify("first day of this month");
    return $dt->format("Y-m-d");
}

/**
 * Returns end date of month in ISO formatted string
 *
 * @param string $period Month in "Y-m" format or date in "Y-m-d" format
 * @return string ISO date
 */
function month_end_date($period)
{
    $dt = date_create_from_format("Y-m", substr($period, 0, 7));

    $dt->modify("last day of this month");
    return $dt->format("Y-m-d");
}
