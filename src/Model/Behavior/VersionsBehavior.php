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
use Cake\Utility\Hash;

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
    'fields' => [],
    'version_field' => '_versions',
  ];

  public function initialize(array $config): void
  {
    parent::initialize($config);
    $this->loadModel('Trois/Versions.Versions');
  }

  public function afterSave(Event $event, EntityInterface $entity, ArrayObject $options)
  {
    // try save version of each fields
    if(!$versions = $this->createVersions($entity, $this->getConfig('fields')))
    {
      $event->stopPropagation();
      return false;
    }

    // attach current version
    $this->attachVersions($entity, $versions);

    return $entity;
  }

  public function afterDelete(Event $event, EntityInterface $entity, ArrayObject $options)
  {
    // set conditions
    $conditions = [
      'model' => $this->table()->getAlias(),
      'foreign_key' => $entity->{ $this->table()->getPrimarykey() },
    ];

    // count rows
    if(!$count = $this->Versions->find()->where($conditions)->count()) return $entity;

    // delete rows and check
    if($this->Versions->deleteAll($conditions) < $count)
    {
      $event->stopPropagation();
      return false;
    }

    return $entity;
  }

  protected function attachVersions(EntityInterface $entity, $versions)
  {
    $structuredVersions = $this->toStructVersionsArray($versions);
    foreach($structuredVersions as $datetime => &$localesArray)
    {
      foreach($localesArray as $locale => &$verionFiledsArray)
      {
        // create entioty from current entity state
        $data = $entity->toArray();
        if(!empty($data['_translations'])) unset($data['_translations']);
        $ve = $this->table()->newEntity($data);

        // merge with current state locale
        $trans = $entity->get('_translations');
        if( is_array($trans) && Hash::check($trans, $locale)) $ve = $this->table()->patchEntity($ve, $trans[$locale]->toArray());

        // modify entity with version state
        foreach($verionFiledsArray as $vf) $ve->set($vf->field, $vf->content);

        // set version owner ship
        $ve->set('owned_by', $vf->user?? $vf->user_id);

        // set entity
        $verionFiledsArray = $ve;
      }
    }

    $entity->set($this->getConfig('version_field'), $structuredVersions);
  }

  protected function toStructVersionsArray(array $versions)
  {
    $sv = [];
    foreach($versions as $version)
    {
      $v = is_array($version)? $this->Versions->newEntity($version): $version;
      $created = $v->created->format('Y-m-d H:i:s');
      $path = "$created.$v->locale";

      // create dim created.locale
      if(Hash::check($sv, $path)) $sv = Hash::merge($sv, Hash::expand([$path => []]));

      // add field for created.locale
      $sv[$created][$v->locale][] = $v;
    }

    // sort array by created DESC
    krsort($sv);

    return $sv;
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
