<?php
declare(strict_types=1);

namespace Trois\Versions\Model\Entity;

use Cake\ORM\Entity;

class EntityVersion extends Entity
{
  protected $_accessible = [
    '*' => true,
  ];
}
