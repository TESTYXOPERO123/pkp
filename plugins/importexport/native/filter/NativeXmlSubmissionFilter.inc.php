<?php

/**
 * @file plugins/importexport/native/filter/NativeXmlSubmissionFilter.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NativeXmlSubmissionFilter
 * @ingroup plugins_importexport_native
 *
 * @brief Base class that converts a Native XML document to a set of submissions
 */

import('lib.pkp.plugins.importexport.native.filter.NativeImportFilter');

class NativeXmlSubmissionFilter extends NativeImportFilter {
	/**
	 * Constructor
	 * @param $filterGroup FilterGroup
	 */
	function __construct($filterGroup) {
		$this->setDisplayName('Native XML submission import');
		parent::__construct($filterGroup);
	}


	//
	// Implement template methods from PersistableFilter
	//
	/**
	 * @copydoc PersistableFilter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.plugins.importexport.native.filter.NativeXmlSubmissionFilter';
	}


	//
	// Implement template methods from NativeImportFilter
	//
	/**
	 * Return the plural element name
	 * @return string
	 */
	function getPluralElementName() {
		$deployment = $this->getDeployment();
		return $deployment->getSubmissionsNodeName();
	}

	/**
	 * Get the singular element name
	 * @return string
	 */
	function getSingularElementName() {
		$deployment = $this->getDeployment();
		return $deployment->getSubmissionNodeName();
	}

	/**
	 * @see Filter::process()
	 * @param $document DOMDocument|string
	 * @return array Array of imported documents
	 */
	function &process(&$document) {
		$importedObjects =& parent::process($document);

		$deployment = $this->getDeployment();
		$submission = $deployment->getSubmission();

		$publication = $submission->getCurrentPublication();
		if (!isset($publication)) {
			$deployment->addError(ASSOC_TYPE_SUBMISSION, $submission->getId(),  __('plugins.importexport.common.error.currentPublicationNullOrMissing'));
		} else {
			// Index imported content
			$articleSearchIndex = Application::getSubmissionSearchIndex();
			foreach ($importedObjects as $submission) {
				assert(is_a($submission, 'Submission'));
				$articleSearchIndex->submissionMetadataChanged($submission);
				$articleSearchIndex->submissionFilesChanged($submission);
			}

			$articleSearchIndex->submissionChangesFinished();
		}
		
		return $importedObjects;
	}

	/**
	 * Handle a singular element import.
	 * @param $node DOMElement
	 */
	function handleElement($node) {
		$deployment = $this->getDeployment();
		$context = $deployment->getContext();

		// Create and insert the submission (ID needed for other entities)
		$submissionDao = DAORegistry::getDAO('SubmissionDAO'); /* @var $submissionDao SubmissionDAO */
		$submission = $submissionDao->newDataObject();

		$submission->setData('contextId', $context->getId());
		$submission->stampLastActivity();
		$submission->setData('status', $node->getAttribute('status'));
		$submission->setData('submissionProgress', 0);

		import('lib.pkp.classes.workflow.WorkflowStageDAO');
		$submission->setData('stageId', WorkflowStageDAO::getIdFromPath($node->getAttribute('stage')));
		$submission->setData('currentPublicationId', $node->getAttribute('current_publication_id'));

		// Handle any additional attributes etc.
		$submission = $this->populateObject($submission, $node);

		$submission = Services::get('submission')->add($submission, Application::get()->getRequest());
		$deployment->setSubmission($submission);

		for ($n = $node->firstChild; $n !== null; $n=$n->nextSibling) {
			if (is_a($n, 'DOMElement')) {
				$this->handleChildElement($n, $submission);
			}
		}
		
		$submission = Services::get('submission')->edit($submission, array(), Application::get()->getRequest());

		return $submission;
	}

	/**
	 * Populate the submission object from the node
	 * @param $submission Submission
	 * @param $node DOMElement
	 * @return Submission
	 */
	function populateObject($submission, $node) {
		$submissionDao = DAORegistry::getDAO('SubmissionDAO'); /* @var $submissionDao SubmissionDAO */
		if ($dateSubmitted = $node->getAttribute('date_submitted')) {
			$submission->setData('dateSubmitted', Core::getCurrentDate(strtotime($dateSubmitted)));
		} else {
			$submission->setData('dateSubmitted', Core::getCurrentDate());
		}

		return $submission;
	}

	/**
	 * Handle an element whose parent is the submission element.
	 * @param $n DOMElement
	 * @param $submission Submission
	 */
	function handleChildElement($n, $submission) {
		switch ($n->tagName) {
			case 'id':
				$this->parseIdentifier($n, $submission);
				break;
			case 'submission_file':
				$this->parseSubmissionFile($n, $submission);
				break;
			case 'publication':
				$publication = $this->parsePublication($n, $submission);

				if ($publication) {
					$publications = $submission->getData('publications');
					$publications[] = $publication[0];
					$submission->setData('publications', $publications);
				}
				
				break;
			default:
				$deployment = $this->getDeployment();
				$deployment->addWarning(ASSOC_TYPE_SUBMISSION, $submission->getId(), __('plugins.importexport.common.error.unknownElement', array('param' => $n->tagName)));
		}
	}

	//
	// Element parsing
	//
	/**
	 * Parse an identifier node and set up the submission object accordingly
	 * @param $element DOMElement
	 * @param $submission Submission
	 */
	function parseIdentifier($element, $submission) {
		$deployment = $this->getDeployment();
		$advice = $element->getAttribute('advice');
		switch ($element->getAttribute('type')) {
			case 'internal':
				// "update" advice not supported yet.
				assert(!$advice || $advice == 'ignore');
				break;
		}
	}

	/**
	 * Parse a submission file and add it to the submission.
	 * @param $n DOMElement
	 * @param $submission Submission
	 */
	function parseSubmissionFile($n, $submission) {
		$importFilter = $this->getImportFilter($n->tagName);
		assert(isset($importFilter)); // There should be a filter

		$importFilter->setDeployment($this->getDeployment());
		$submissionFileDoc = new DOMDocument();
		$submissionFileDoc->appendChild($submissionFileDoc->importNode($n, true));
		return $importFilter->execute($submissionFileDoc);
	}

	/**
	 * Parse a submission publication and add it to the submission.
	 * @param $n DOMElement
	 * @param $submission Submission
	 */
	function parsePublication($n, $submission) {
		$importFilter = $this->getImportFilter($n->tagName);
		assert(isset($importFilter)); // There should be a filter

		$importFilter->setDeployment($this->getDeployment());
		$submissionFileDoc = new DOMDocument();
		$submissionFileDoc->appendChild($submissionFileDoc->importNode($n, true));
		return $importFilter->execute($submissionFileDoc);
	}

	//
	// Helper functions
	//

	/**
	 * Get the import filter for a given element.
	 * @param $elementName string Name of XML element
	 * @return Filter
	 */
	function getImportFilter($elementName) {
		assert(false); // Subclasses should override
	}
}


