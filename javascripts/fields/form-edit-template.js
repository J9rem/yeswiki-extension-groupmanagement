/*
 * This file is part of the YesWiki Extension groupmanagement.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

registerFieldGroupmanagement(getAclSelectField(readConf,writeconf,aclsOptions,aclsCommentOptions,defaultMapping,templateHelper))
registerFieldGroupmanagement(getAutoRegisterGroupField(defaultMapping))
registerFieldGroupmanagement(getAutoRegisterLinkedEntryField(lists,templateHelper))