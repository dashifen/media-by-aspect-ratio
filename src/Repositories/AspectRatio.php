<?php

namespace Dashifen\MediaByAspectRatio\Repositories;

use Dashifen\Repository\Repository;
use Dashifen\Repository\RepositoryException;

/**
 * Class AspectRatio
 *
 * @property-read int    $width
 * @property-read int    $height
 * @property-read string $name
 * @property-read float  $ratio
 *
 * @package Dashifen\MediaByAspectRatio\Repositories
 */
class AspectRatio extends Repository
{
  public const PRECISION = 3;
  
  protected int $width;
  protected int $height;
  protected string $name;
  
  /**
   * AspectRatio constructor.
   *
   * @param int    $width
   * @param int    $height
   * @param string $name
   *
   * @throws RepositoryException
   */
  public function __construct(int $width, int $height, string $name = '')
  {
    parent::__construct([
      'width'  => $width,
      'height' => $height,
      'name'   => $name,
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
   * Returns the value of our getName method which is slightly more complicated
   * than would be typical.  See below for more information.
   *
   * @return string
   */
  public function __toString()
  {
    return $this->getName();
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
  
  /**
   * setName
   *
   * Sets the name property.
   *
   * @param string $name
   *
   * @return void
   */
  protected function setName(string $name): void
  {
    $this->name = $name;
  }
  
  /**
   * getName
   *
   * Returns either the name property or, when it's empty, the width and height
   * in the format of W:H, e.g. 16:9.  Remember:  Repository getters are
   * automatically called by the __get method when they're available.
   *
   * @return string
   */
  public function getName(): string
  {
    $ratio = $this->width . ':' . $this->height;
    return !empty($this->name) ? $this->name . ' (' . $ratio . ')' : $ratio;
  }
}
