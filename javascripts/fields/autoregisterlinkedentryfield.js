/*
 * This file is part of the YesWiki Extension groupmanagement.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

function getAutoRegisterLinkedEntryField(listsMapping,templateHelper){

    return {
        field: {
          label: 'Être enregistré·e comme membre de cette structure lors de la création de fiche ?',
          name: "autoregisterlinkedentry",
          attrs: { type: "autoregisterlinkedentry" },
          icon: '<i class="fas fa-external-link-alt"></i>',
        },
        attributes: {
          listeOrFormId: {
            label: _t('BAZ_FORM_EDIT_SELECT_SUBTYPE2_FORM'),
            options: {
              ...formAndListIds.forms,
            },
          },
          formId: {
            label: "",
            options: formAndListIds.forms,
          },
          linkedFieldName: {
            label: "Nom du champ lié",
            value: "",
            placeholder: "Si plusieurs, les séparer par des virgules"
          },
          defaultValue: {
            label: _t('BAZ_FORM_EDIT_SELECT_DEFAULT'),
            options: {
              "oui": _t('YES'),
              "no": _t('NO'),
            },
          },
        },
        attributesMapping: {
          ...listsMapping,
          ...{
            6: "",
            7: "linkedFieldName",
            8: "",
            9: "",
          }
        },
        renderInput(field) {
          return {
            field: '<span></span><input type="checkbox" checked/> Oui',
            onRender: function(){
              templateHelper.defineLabelHintForGroup(field,'listeOrFormId','Choisir le formulaire des fiches filles');
              
              templateHelper.prependHTMLBeforeGroup(field,'linkedFieldName',$('<div/>').addClass('form-group')
                .append('Donner le nom du champ concerné dans ce formulaire.')
                .append('<br/>')
                .append('Si vide, tous les champs radio/liste/checkboxfiche pointant vers le présent formualire seront mis à jour.'));
            },
          };
        },
        disabledAttributes: ['required','name','value']
    }
}