<?php

function idr_to_usd_rate() {
    $rate = getenv('IDR_TO_USD_RATE');
    return $rate !== false && (float)$rate > 0 ? (float)$rate : 15500;
}

function idr_to_usd($value) {
    return (float)$value / idr_to_usd_rate();
}

function dollar($value, $decimals = 2) {
    return '$' . number_format((float)$value, $decimals, '.', ',');
}

function dollar_value($value, $decimals = 2) {
    return round((float)$value, $decimals);
}
