<?php
// แปลงชื่อเดือนเป็นภาษาไทย
function thaiMonthName($month)
{
    $thaiMonths = [
        1 => 'มกราคม',
        2 => 'กุมภาพันธ์',
        3 => 'มีนาคม',
        4 => 'เมษายน',
        5 => 'พฤษภาคม',
        6 => 'มิถุนายน',
        7 => 'กรกฎาคม',
        8 => 'สิงหาคม',
        9 => 'กันยายน',
        10 => 'ตุลาคม',
        11 => 'พฤศจิกายน',
        12 => 'ธันวาคม',
    ];

    return isset($thaiMonths[(int)$month]) ? $thaiMonths[(int)$month] : '';
}

// แปลงปี ค.ศ. เป็น พ.ศ.
function toBuddhistYear($year)
{
    return ((int)$year) + 543;
}

// แปลงวันที่แบบเต็ม เป็นวันที่ภาษาไทย เช่น 1 มกราคม 2566 (อาทิตย์)
function thaiDateFull($dateStr)
{
    $dt = new DateTime($dateStr);
    $day = $dt->format('j');
    $month = thaiMonthName((int)$dt->format('n'));
    $year = toBuddhistYear((int)$dt->format('Y'));
    $weekdayThai = ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'];
    $weekday = $weekdayThai[(int)$dt->format('w')];

    return $day . ' ' . $month . ' ' . $year . ' (' . $weekday . ')';
}
