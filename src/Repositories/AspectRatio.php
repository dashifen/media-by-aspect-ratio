<?php

namespace Dashifen\MediaByAspectRatio\Repositories;

use Dashifen\Repository\Repository;
use Dashifen\Repository\RepositoryException;

/**
 * Class AspectRatio
 *
 * @property-read int   $width
 * @property-read int   $height
 * @property-read float $ratio
 *
 * @package Dashifen\MediaByAspectRatio\Repositories
 */
class AspectRatio extends Repository
{
  public const PRECISION = 3;
  
  protected int $width;
  protected int $height;
  
  /**
   * AspectRatio constructor.
   *
   * @param int $width
   * @param int $height
   *
   * @throws RepositoryException
   */
  public function __construct(int $width, int $height)
  {
    parent::__construct([
      'width'  => $width,
      'height' => $height,
    ]);
  }
  
  /**
   * __get
   *
   * If the ratio pseudo-property is requested, calculate and return it.
   * Otherwise, pass control off to our parent's __get method
   *
   * @param string $property
   *
   * @return mixed
   * @throws RepositoryException
   */
  public function __get(string $property)
  {
    return $property === 'ratio'
      ? round($this->width / $this->height, self::PRECISION)
      : parent::__get($property);
  }
  
  /**
   * __toString
   *
   * Prints the aspect ratio as $width:$height (e.g. 16:9).
   *
   * @return string
   */
  public function __toString()
  {
    return $this->width . ':' . $this->height;
  }
  
  /**
   * setWidth
   *
   * Sets the width property.
   *
   * @param int $width
   *
   * @return void
   * @throws RepositoryException
   */
  protected function setWidth(int $width): void
  {
    if ($width <= 0) {
      throw new RepositoryException('Invalid width: ' . $width,
        RepositoryException::INVALID_VALUE);
    }
    
    $this->width = $width;
  }
  
  /**
   * setHeight
   *
   * Sets the height property.
   *
   * @param int $height
   *
   * @return void
   * @throws RepositoryException
   */
  protected function setHeight(int $height): void
  {
    if ($height <= 0) {
      throw new RepositoryException('Invalid height: ' . $height,
        RepositoryException::INVALID_VALUE);
    }
    
    $this->height = $height;
  }
}
