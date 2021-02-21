<?php

namespace Core;

/* Inspired by
  → https://www.codexworld.com/php-url-shortener-library-create-short-url/
  → https://github.com/ircmaxell/RandomLib/blob/master/lib/RandomLib/Generator.php
*/

abstract class Generator
{
  const CHARS_LOWER = 1;
  const CHARS_UPPER = 2;
  const CHARS_DIGITS = 4;
  const CHARS_SYMBOLS = 8;

  // Default: "B8G6I1l|0OQDS5Z2"
  const AMBIGUOUS_CHARS = 'I1l0O';

  private static $alphabets = [
    self::CHARS_LOWER => 'abcdefghijklmnopqrstuvwxyz',
    self::CHARS_UPPER => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
    self::CHARS_DIGITS => '0123456789',
    self::CHARS_SYMBOLS => '-_'
  ];

  public static function RandomCode($length = 5, $easyToRead = false, $chars = self::CHARS_LOWER | self::CHARS_UPPER | self::CHARS_DIGITS)
  {
    if ($length < 3)
      throw new \Exception('Minimum length is 3 characters.');

    $random = '';
    $alphabet = '';

    foreach (self::$alphabets as $char => $set)
    {
      if (($chars & $char) === $char)
      {
        if ($easyToRead)
        {
          $set = str_replace(str_split(self::AMBIGUOUS_CHARS), '', $set);
        }

        $random .= $set[array_rand(str_split($set))];
        $alphabet .= $set;
      }
    }
    $alphabet = str_split($alphabet);

    for ($i = 0, $l = strlen($random); $i < $length - $l; $i++)
    {
      $random .= $alphabet[array_rand($alphabet)];
    }

    return str_shuffle($random);
  }
}
