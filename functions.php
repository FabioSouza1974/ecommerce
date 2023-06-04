<?php

use Hcode\Model\Cart;
use Hcode\Model\User;

function formatPrice($vlprice)
{
    if (!$vlprice > 0) {
        $vlprice = 0;
    }

    return number_format($vlprice, 2, ',', '.');
}

function formatDate($date)
{
    return date('d/m/Y', strtotime($date));
}

function checkLogin($inadmin = true)
{
    return User::checkLogin($inadmin);
}

function getUserName()
{
    $user = User::getFromSession();

    return $user->getdesperson();
}

function getCartNroQtd()
{
    $cart = Cart::getFromSession();

    $totals = $cart->getProductsTotals();

    return $totals['nrqtd'];
}

function getCartVlSubTotal()
{
    $cart = Cart::getFromSession();

    $totals = $cart->getProductsTotals();

    return formatPrice($totals['vlprice']);
}

function encode_utf8($string)
{
    return mb_convert_encoding($string, 'UTF-8', mb_detect_encoding($string));
    //return mb_convert_encoding($string, 'UTF-8', 'ISO-8859-1');
}

function decode_utf8($string)
{
    return mb_convert_encoding($string, mb_detect_encoding($string), 'UTF-8');
    //return mb_convert_encoding($string, 'ISO-8859-1', 'UTF-8'); /
}
