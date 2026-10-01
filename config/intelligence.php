<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Recent activity
    |--------------------------------------------------------------------------
    */
    'activity_limit' => (int) env('INTELLIGENCE_ACTIVITY_LIMIT', 10),

    /*
    |--------------------------------------------------------------------------
    | Forecast
    |--------------------------------------------------------------------------
    |
    | Uses completed historical calendar months only (current month excluded)
    | so partial months are not compared against full months.
    */
    'forecast_lookback_months' => (int) env('INTELLIGENCE_FORECAST_LOOKBACK_MONTHS', 3),
    'forecast_category_limit' => (int) env('INTELLIGENCE_FORECAST_CATEGORY_LIMIT', 5),

    /*
    |--------------------------------------------------------------------------
    | Large / unusual spending detection
    |--------------------------------------------------------------------------
    |
    | Flag an expense when amount >= multiplier × category average (excluding
    | the transaction itself) within the recent lookback window, and absolute
    | amount meets the minimum floor. Defaults are intentionally stricter than
    | a raw 2× / 3-observation rule to reduce false positives for students.
    */
    'large_min_observations' => (int) env('INTELLIGENCE_LARGE_MIN_OBSERVATIONS', 5),
    'large_multiplier' => (float) env('INTELLIGENCE_LARGE_MULTIPLIER', 2.5),
    'large_absolute_floor' => (float) env('INTELLIGENCE_LARGE_ABSOLUTE_FLOOR', 500),
    'large_lookback_days' => (int) env('INTELLIGENCE_LARGE_LOOKBACK_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Duplicate detection
    |--------------------------------------------------------------------------
    |
    | Expenses only. Same normalized merchant/description + exact amount.
    | High confidence = same calendar day. Moderate = within ± window days
    | when DUPLICATE_DATE_WINDOW_DAYS > 0. Never auto-deletes transactions.
    */
    'duplicate_date_window_days' => (int) env('INTELLIGENCE_DUPLICATE_DATE_WINDOW_DAYS', 0),

];
