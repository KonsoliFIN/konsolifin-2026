<?php

namespace Drupal\migrate_konsolifin\Utility;

final class MigrateMath {

  public static function add25($value) {
    return is_numeric($value) ? $value + 25 : NULL;
  }

  public static function multiplyBy32($value) {
    return is_numeric($value) ? intval($value * 3.2) : NULL;
  }

}