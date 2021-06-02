<?php
namespace Trois\Versions\Model\Behavior;

use ArrayObject;

use Cake\Event\Event;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Behavior;
use Cake\ORM\Table;
use Cake\I18n\I18n;
use Cake\Core\Configure;
use Cake\Utility\Text;
use Cake\Datasource\ModelAwareTrait;
use Cake\Routing\Router;

/**
* Versions behavior
*/
class VersionsBehavior extends Behavior
{

  use ModelAwareTrait;

  /**
  * Default configuration.
  *
  * @var array
  */
  protected $_defaultConfig = [
    'translate' => false,
    'retention' => [
      'max_amount' => 10, // -1 => no limit
      'max_periode' => '12 months', // -1 => no limit
    ],
    'fields' => ['content'],
    'version_field' => '_versions',
  ];

  public function initialize(array $config): void
  {
    parent::initialize($config);
    $this->loadModel('Trois/Versions.Versions');
  }

  public function afterSave(Event $event, EntityInterface $entity, ArrayObject $options)
  {
    $entity->set(
      $this->getConfig('version_field'),
      $this->createVersions($entity, $this->getConfig('fields'))
    );
    return $entity;
  }

  public function afterDelete(Event $event, EntityInterface $entity, ArrayObject $options)
  {
    $versions = $entity->get($this->getConfig('version_field'));

    if($versions && !empty($versions)) $this->Versions->deleteAll([
      'model' => $this->table()->getAlias(),
      'foreign_key' => $entity->{ $this->table()->getPrimarykey() },
    ]);

    return $entity;
  }

  protected function tryGetUserId()
  {
    if(!Router::getRequest()) return null;
    if(!$identity = Router::getRequest()->getAttribute('identity')) return null;
    if(empty($identity['id'])) return null;
    return $identity['id'];
  }

  protected function createVersions(EntityInterface $entity, $fields)
  {
    $versions = [];
    $toMerge = [
      'created' => new \DateTime(),
      'version_id' => Text::uuid(),
      'model' => $this->table()->getAlias(),
      'foreign_key' => $entity->{ $this->table()->getPrimarykey() },
      'user_id' => $this->tryGetUserId()
    ];

    foreach ($fields as $field)
    {
      // regular
      $versions[] = $this->createVerionForField($field, $entity->get($field), Configure::read('App.defaultLocale'), $toMerge);

      // translation
      if($this->getConfig('translate') && !empty($entity->get('_translations')))
      {
        foreach ($entity->get('_translations') as $locale => $i18nEntity) $versions[] = $this->createVerionForField($field, $i18nEntity->get($field), $locale, $toMerge);
      }
    }

    return $this->Versions->saveMany($versions);
  }

  protected function createVerionForField($field, $value, $locale, $toMerge = [])
  {
    return $this->Versions->newEntity(array_merge($toMerge,[
      'field' => $field,
      'content' => $value,
      'locale' => $locale
    ]));
  }
}
