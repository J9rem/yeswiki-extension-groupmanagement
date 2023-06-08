/*
 * This file is part of the YesWiki Extension groupmanagement.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import {readConf,writeconf,aclsOptions,aclsCommentOptions,defaultMapping,listsMapping} from '../../../bazar/presentation/javascripts/form-edit-template/fields/commons/attributes.js'
import templateHelper from '../../../bazar/presentation/javascripts/form-edit-template/fields/commons/render-helper.js'

registerFieldAsModuleGroupmanagement(getAclSelectField(readConf,writeconf,aclsOptions,aclsCommentOptions,defaultMapping,templateHelper))
registerFieldAsModuleGroupmanagement(getAutoRegisterGroupField(defaultMapping))
registerFieldAsModuleGroupmanagement(getAutoRegisterLinkedEntryField(listsMapping,templateHelper))
