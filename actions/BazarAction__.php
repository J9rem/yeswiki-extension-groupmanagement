<?php

namespace YesWiki\Groupmanagement;

use BazarAction;
use YesWiki\Core\YesWikiAction;

class BazarAction__ extends YesWikiAction
{
    public function run()
    {
        if (!$this->isWikiHibernated()
            && $this->wiki->UserIsAdmin()
            && isset($this->arguments[BazarAction::VARIABLE_VOIR]) && $this->arguments[BazarAction::VARIABLE_VOIR] === BazarAction::VOIR_FORMULAIRE
            && isset($this->arguments[BazarAction::VARIABLE_ACTION]) && in_array($this->arguments[BazarAction::VARIABLE_ACTION], [BazarAction::ACTION_FORM_CREATE,BazarAction::ACTION_FORM_EDIT], true)
            ) {
            $this->wiki->AddJavascriptFile('tools/groupmanagement/javascripts/autoregisterlinkedentry/form-edit-template.js');
        }
    }
}
