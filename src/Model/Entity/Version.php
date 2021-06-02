<?php
declare(strict_types=1);

namespace Trois\Versions\Model\Entity;

use Cake\ORM\Entity;

/**
 * Version Entity
 *
 * @property string $id
 * @property string $version_id
 * @property string $model
 * @property string $foreign_key
 * @property string|null $locale
 * @property string $field
 * @property string|null $content
 * @property \Cake\I18n\FrozenTime $created
 * @property string|null $user_id
 *
 * @property \Trois\Versions\Model\Entity\Version[] $versions
 * @property \Trois\Versions\Model\Entity\User $user
 */
class Version extends Entity
{

    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
     *
     * @var array
     */
     protected $_accessible = [
       '*' => true,         
      'id' => false,
            ];
}
