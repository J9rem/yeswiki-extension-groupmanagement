<?php

namespace YesWiki\Groupmanagement;

use YesWiki\Core\YesWikiAction;
use YesWiki\Core\Service\UserManager;

class GroupManagementAction extends YesWikiAction
{
    protected $userManager;
    private $options;

    public function run()
    {
        // get Services
        $this->userManager = $this->getService(UserManager::class);
        
        $errorMsg = "";

        $this->options = [];
        $optionsReady = $this->getOptions();

        if (filter_input(INPUT_POST, 'view', FILTER_SANITIZE_STRING) == "options") {
            return $this->manageOptions();
        }
        
        $user = $this->userManager->getLoggedUser();
        if (!$user) {
            $errorMsg = _t('GRPMNGT_ACTION_NO_USER');
        } elseif (!$optionsReady) {
            $errorMsg = _t('GRPMNGT_ACTION_NO_OPTIONS');
        } else {
            // TODO find groups with right suffix where the user is
            // TODO find entries corresponding to theses groups
            // TODO find entries where current user is owner
            // TODO propose to select one entry only if several
            // TODO extract list of members of the selected group
            // TODO extract list of members linked to this entry (owners of the linked entries)
            // TODO display checkbox drag and drop
            // TODO save values
        }

        return $this->render("@groupmanagement/actions/groupmanagement.twig", [
            'isAdmin' => $this->wiki->UserIsAdmin(),
            'errorMsg' => $errorMsg,
        ]);
    }

    private function manageOptions(): ?string
    {
        // TODO set data if needed
        return $this->render("@groupmanagement/actions/groupmanagement-options.twig", [
            'options' => $this->option,
        ]);
    }

    private function getOptions(): bool
    {
        // TODO get data from tiples
        // TODO check if everything is defined
        return false;
    }
}
