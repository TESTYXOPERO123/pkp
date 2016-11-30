{**
 * templates/submission/submissionMetadataFormTitleFields.tpl
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Submission's metadata form title fields. To be included in any form that wants to handle
 * submission metadata.
 *}
{if count($supportedSubmissionLocaleNames) == 1}
	{* There is only one supported submission locale; choose it invisibly *}
	{foreach from=$supportedSubmissionLocaleNames item=localeName key=locale}
		<input type="hidden" name="locale" value="{$locale|escape}" />
	{/foreach}
{else}
	{* There are several submission locales available; allow choice *}
	{fbvFormSection title="submission.submit.submissionLocale" for="locale"}
		{fbvElement type="select" label="submission.submit.submissionLocaleDescription" id="locale" from=$supportedSubmissionLocaleNames selected=$locale translate=false readonly=$readOnly size=$fbvStyles.size.MEDIUM}
	{/fbvFormSection}
{/if}{* count($supportedSubmissionLocaleNames) == 1 *}

{if $formParams.revision && ($formParams.revision < $latestRevisionId)}
	{assign var=readOnly value=1}
{else}
	{assign var=readOnly value=0}
{/if}

{fbvElement type="hidden" name="submissionRevision" id="submissionRevision" value=$formParams.revision}
{fbvElement type="hidden" name="saveAsRevision" id="saveAsRevision" value=$formParams.saveAsRevision}

<div class="pkp_helpers_clear">
	{fbvFormSection for="title" title="common.prefix" inline="true" size=$fbvStyles.size.SMALL}
		{fbvElement label="common.prefixAndTitle.tip" type="text" multilingual=true name="prefix" id="prefix" value=$prefix readonly=$readOnly maxlength="32"}
	{/fbvFormSection}
	{fbvFormSection for="title" title="common.title" inline="true" size=$fbvStyles.size.LARGE required=true}
		{fbvElement type="text" multilingual=true name="title" id="title" value=$title readonly=$readOnly maxlength="255" required=true}
	{/fbvFormSection}
</div>
{fbvFormSection title="common.subtitle" for="subtitle"}
	{fbvElement label="common.subtitle.tip" type="text" multilingual=true name="subtitle" id="subtitle" value=$subtitle readonly=$readOnly}
{/fbvFormSection}
{fbvFormSection title="common.abstract" for="abstract" required=$abstractsRequired}
	{fbvElement type="textarea" multilingual=true name="abstract" id="abstract" value=$abstract rich="extended" readonly=$readOnly}
{/fbvFormSection}
