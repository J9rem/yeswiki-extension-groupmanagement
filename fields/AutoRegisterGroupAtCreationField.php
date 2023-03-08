<?php

/*
 * This file is part of the YesWiki Extension groupmanagement.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace YesWiki\Groupmanagement\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Bazar\Field\BazarField;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\UserManager;
use YesWiki\Groupmanagement\Controller\GroupController;

/**
 * @Field({"autoregistergroup"})
 */
class AutoRegisterGroupAtCreationField extends BazarField
{
    protected const FIELD_PAGE_FOR_OPTIONS = 4;

    protected $pageForOptions;

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->pageForOptions = $values[self::FIELD_PAGE_FOR_OPTIONS] ?? '';
        $this->pageForOptions = (!is_string($this->pageForOptions) || empty(trim($this->pageForOptions))) 
            ? '' 
            : trim($this->pageForOptions);
        $this->maxChars = '';
    }

    protected function renderInput($entry)
    {
        return null;
    }

    protected function renderStatic($entry)
    {
        return null;
    }
    
    public function canRead($entry, ?string $userNameForRendering = null)
    {
        return true;
    }

    public function canEdit($entry, bool $isCreation = false)
    {
        return true;
    }

    public function formatValuesBeforeSaveIfEditable($entry, bool $isCreation = false)
    {
        if ($isCreation){
            return $this->formatValuesBeforeSave($entry);
        } else {
            return [];
        }
    }

    public function formatValuesBeforeSave($entry)
    {
        if (!empty($this->pageForOptions) && !empty($entry['id_fiche']) && is_string($entry['id_fiche'])){
            // get services
            $groupController = $this->getService(GroupController::class);
            $pageManager = $this->getService(PageManager::class);
            $userManager = $this->getService(UserManager::class);

            $user = $userManager->getLoggedUser();
            if (!empty($user)){
                $optionPage = $pageManager->getOne($this->pageForOptions,null,false,true);
                if (!empty($optionPage)){
                    $options = [];
                    $associatedFields = [];
                    if ($groupController->getOptions($this->pageForOptions,$options,$associatedFields)){
                        $groupController->saveGroup($entry['id_fiche'],[$user['name']=>1],$options);
                    }
                }
            }
        }
        return [];
    }

    public function getPageForOptions()
    {
        return $this->pageForOptions;
    }

    // change return of this method to keep compatible with php 7.3 (mixed is not managed)
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        $array = array_merge(
            parent::jsonSerialize(),
            [
                'pageForOptions' => $this->getPageForOptions()
            ]
        );
        return $array;
    }
}
