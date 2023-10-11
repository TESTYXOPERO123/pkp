<?php

/**
 * @file api/v1/_18n/I18nController.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I18nController
 *
 * @ingroup api_v1__i18n
 *
 * @brief Handle API requests for backend operations.
 *
 */

namespace PKP\API\v1\_i18n;

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PKP\core\PKPBaseController;
use PKP\facades\Locale;

class I18nController extends PKPBaseController
{
    /**
     * @copydoc \PKP\core\PKPBaseController::getHandlerPath()
     */
    public function getHandlerPath(): string
    {
        return '_i18n';
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getRouteGroupMiddleware()
     */
    public function getRouteGroupMiddleware(): array
    {
        return [];
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getGroupRoutes()
     */
    public function getGroupRoutes(): void
    {       
        Route::get('ui.js', $this->getTranslations(...))->name('_i18n.getTranslations');
    }

    /**
     * Constructor
     */
    // public function __construct()
    // {
    //     $this->_handlerPath = '_i18n';
    //     $endpoints = [
    //         'GET' => [
    //             [
    //                 'pattern' => $this->getEndpointPattern() . '/ui.js',
    //                 'handler' => [$this, 'getTranslations'],
    //             ]
    //         ]
    //     ];

    //     $this->_endpoints = $endpoints;

    //     parent::__construct();
    // }

    /**
     * Provides javascript file which includes all translations used in Vue.js UI.
     */
    public function getTranslations(Request $illuminateRequest): Response
    {

        $translations = Locale::getUiTranslator()->getTranslationStrings();

        $jsContent = 'window.pkp = window.pkp || {}; pkp.localeKeys = ' . json_encode($translations, JSON_FORCE_OBJECT) . ';';

        // $response->getBody()->write($jsContent);

        // return $response
        //     ->withHeader('Content-Type', 'application/javascript')
        //     // cache for one year, hash is provided as query param, which ensures fetching updated version when needed
        //     ->withHeader('Cache-Control', 'public, max-age=31536000');

        return response()
            ->setContent($jsContent)
            ->header('Content-Type', 'application/javascript')
            // // cache for one year, hash is provided as query param, which ensures fetching updated version when needed
            ->header('Cache-Control', 'public, max-age=31536000');

    }
}
