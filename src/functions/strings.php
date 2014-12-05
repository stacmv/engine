<?php
function mb_ucfirst($str, $enc = 'utf-8') {
    return mb_strtoupper(mb_substr($str, 0, 1, $enc), $enc).mb_strtolower(mb_substr($str, 1, mb_strlen($str, $enc), $enc), $enc);
} 