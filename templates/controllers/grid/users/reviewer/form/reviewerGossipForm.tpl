{**
 * templates/controllers/grid/users/reviewer/form/reviewerGossipForm.tpl
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Display form to gossip about a user.
 *}
<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#reviewerGossipForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>
<form class="pkp_form" id="reviewerGossipForm" method="post" action="{url op="gossip" params=$requestArgs}" >
	{csrf}

    <h3>{translate key="user.gossip"}</h3>
	{fbvFormSection}
		{fbvElement type="textarea" name="gossip" id="gossip" label="user.gossip.description" rich=true value=$gossip}
	{/fbvFormSection}

    <h3>{translate key="user.private.notes"}</h3>
    {fbvFormSection}
        {fbvElement type="textarea" name="privateNote" id="privateNote" label="user.private.notes.description" rich=true value=$privateNote}
    {/fbvFormSection}

	{fbvFormButtons}
</form>
