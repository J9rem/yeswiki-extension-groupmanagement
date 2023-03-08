/*
 * This file is part of the YesWiki Extension groupmanagement.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

typeUserAttrs = {
  ...typeUserAttrs,
  ...{
      "autoregistergroup": {
        pageForOptions: {
          label: _t('GRPMNGT_AUTOREGISTERGROUP_PAGEFOROPTIONS_LABEL'),
          value: '',
          placeholder: _t('GRPMNGT_AUTOREGISTERGROUP_PAGEFOROPTIONS_PLACEHOLDER')
        }
      }
    }
};

templates = {
  ...templates,
  ...{
    autoregistergroup(field) {
      return { field: '' }
    }
  }
};

yesWikiMapping = {
  ...yesWikiMapping,
  ...{
    "autoregistergroup": {
      ...defaultMapping,
      ...{
        4: "pageForOptions",
      }
    }
  }
};

typeUserDisabledAttrs['autoregistergroup'] = ['required','name','value'];

fields.push({
    label: _t('GRPMNGT_AUTOREGISTERGROUP_LABEL'),
    name: "autoregistergroup",
    attrs: { type: "autoregistergroup" },
    icon: '<i class="fas fa-magic"></i>',
  });
