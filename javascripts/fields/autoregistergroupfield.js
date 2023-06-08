/*
 * This file is part of the YesWiki Extension groupmanagement.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

function getAutoRegisterGroupField(defaultMapping){

    return {
        field: {
          label: _t('GRPMNGT_AUTOREGISTERGROUP_LABEL'),
          name: "autoregistergroup",
          attrs: { type: "autoregistergroup" },
          icon: '<i class="fas fa-magic"></i>',
        },
        attributes: {
          pageForOptions: {
            label: _t('GRPMNGT_AUTOREGISTERGROUP_PAGEFOROPTIONS_LABEL'),
            value: '',
            placeholder: _t('GRPMNGT_AUTOREGISTERGROUP_PAGEFOROPTIONS_PLACEHOLDER')
          }
        },
        attributesMapping: {
          ...defaultMapping,
          ...{
            4: "pageForOptions",
          }
        },
        renderInput(field) {
          return { field: '' }
        },
        disabledAttributes: ['required','name','value']
    }
}