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
    "aclselect": {
      hint: {label: _t('BAZ_FORM_EDIT_HELP'), value:_t('GRPMNGT_ACLSELECT_SUFFIX_HINT')},
      read: readConf,
      write: writeconf,
      suffix: { label: _t('GRPMNGT_ACLSELECT_SUFFIX_LABEL'), value: "Members"},
      linkfield: { label: _t('GRPMNGT_ACLSELECT_LINKFIELD_LABEL'), value: "bf_structure"},
      readEntry: { label: _t('BAZ_FORM_EDIT_ACL_READ_LABEL'), options: aclsOptions, multiple: true },
      writeEntry: { label: _t('BAZ_FORM_EDIT_ACL_WRITE_LABEL'), options: aclsOptions, multiple: true },
      commentEntry: { label: _t('BAZ_FORM_EDIT_ACL_COMMENT_LABEL'), options: aclsCommentOptions, multiple: true },
    }
  }
};

templates = {
  ...templates,
  ...{
    aclselect: function (field) {
      return {
        field: `<span class="radio">
          <input type="radio" disabled name="aclselect" value="public" checked/>
          <span></span> ${_t('GRPMNGT_ACLSELECT_PUBLIC')}</span>
          <span class="radio">
          <input disabled name="aclselect" value="protected" type="radio"/>
          <span></span> ${_t('GRPMNGT_ACLSELECT_LIMITED_TO_MEMBERS')}
          </span>`,
        onRender: function(){
          templateHelper.defineLabelHintForGroup(field,'suffix',_t('GRPMNGT_ACLSELECT_SUFFIX_HINT'));
        },
      };
    },
  }
};

yesWikiMapping = {
  ...yesWikiMapping,
  ...{
    "aclselect": {
      ...defaultMapping,
      ...{
        3: "readEntry",
        4: "writeEntry",
        8: "commentEntry",
        9: "suffix",
        13: "linkfield"
      }
    }
  }
};

typeUserDisabledAttrs['aclselect'] = ['required'];

fields.push({
  label: _t('BAZ_FORM_EDIT_ACLSELECT_LABEL'),
  name: "aclselect",
  attrs: { type: "aclselect" },
  icon: '<i class="fas fa-user-lock"></i>',
});
