/*
Plugin Name: FWK Content Manager - Import, De-duplicate & Bulk Update
Description: Import content from API or XML, detect and remove duplicates, and perform bulk updates across any post type with venue/organizer handling
Version: 4.1
*/

if (!defined('ABSPATH')) exit;

add_action('admin_menu', function() {
    add_management_page('Content Manager', 'Content Manager', 'manage_options', 'fwk-content-manager', 'fwk_content_manager_page');
});

add_action('wp_ajax_fwk_acf_import_batch', 'fwk_ajax_acf_import_batch');
add_action('wp_ajax_fwk_acf_get_total_count', 'fwk_ajax_acf_get_total_count');
add_action('wp_ajax_fwk_acf_upload_xml', 'fwk_ajax_acf_upload_xml');
add_action('wp_ajax_fwk_acf_import_xml_batch', 'fwk_ajax_acf_import_xml_batch');
add_action('wp_ajax_fwk_scan_duplicates', 'fwk_ajax_scan_duplicates');
add_action('wp_ajax_fwk_process_duplicate_batch', 'fwk_ajax_process_duplicate_batch');
add_action('wp_ajax_fwk_merge_duplicate', 'fwk_ajax_merge_duplicate');
add_action('wp_ajax_fwk_delete_duplicate', 'fwk_ajax_delete_duplicate');
add_action('wp_ajax_fwk_bulk_update_posts', 'fwk_ajax_bulk_update_posts');

function fwk_content_manager_page() {
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    ?>
    <div class="wrap">
        <h1>Content Manager</h1>
        
        <!-- Tab Navigation -->
        <h2 class="nav-tab-wrapper">
            <a href="#import-tab" class="nav-tab nav-tab-active" id="import-tab-link">Import</a>
            <a href="#deduplicate-tab" class="nav-tab" id="deduplicate-tab-link">De-duplicate</a>
            <a href="#update-tab" class="nav-tab" id="update-tab-link">Bulk Update</a>
        </h2>

        <!-- IMPORT TAB -->
        <div id="import-tab" class="tab-content">
        <div id="fwk-import-form">
            <h2>Choose Import Source</h2>
            <table class="form-table">
                <tr>
                    <th><label for="post_type">Post Type</label></th>
                    <td>
                        <input type="text" id="post_type" class="regular-text" list="post_type_suggestions" value="tribe_events" placeholder="Select or enter custom post type">
                        <datalist id="post_type_suggestions">
                            <option value="tribe_events">Events (tribe_events)</option>
                            <option value="events">Events (events)</option>
                            <option value="post">Posts (post)</option>
                            <option value="page">Pages (page)</option>
                            <option value="event-venue">Event Venue</option>
                            <option value="event-organizer">Event Organizer</option>
                        </datalist>
                        <p class="description">Select a common post type or enter a custom one (e.g., tribe_events, post, page, custom_post_type)</p>
                    </td>
                </tr>
                <tr>
                    <th>Import Method</th>
                    <td>
                        <fieldset style="margin: 0; padding: 0;">
                            <label style="display: block; padding: 10px; margin-bottom: 5px; background: #f0f0f0; border-radius: 4px; cursor: pointer;">
                                <input type="radio" name="import_method" value="api" checked style="margin-right: 10px;"> 
                                <strong>API</strong> - Fetch from URL (requires source site to be online)
                            </label>
                            <label style="display: block; padding: 10px; background: #f0f0f0; border-radius: 4px; cursor: pointer;">
                                <input type="radio" name="import_method" value="xml" style="margin-right: 10px;"> 
                                <strong>XML File Upload</strong> - Import from WordPress export file
                            </label>
                        </fieldset>
                    </td>
                </tr>
            </table>

            <div id="api-options">
                <h3>API Import Options</h3>
                <table class="form-table">
                    <tr>
                        <th><label for="source_url">Source API URL</label></th>
                        <td>
                            <input type="url" id="source_url" class="regular-text" value="https://www.fortworthkey.org/wp-json/fwk/v1/export-posts?post_type=tribe_events">
                            <p class="description">Full URL to the source site's export-posts API endpoint</p>
                        </td>
                    </tr>
                </table>
            </div>

            <div id="xml-options" style="display:none; background: #f0f6fc; padding: 15px; border: 1px solid #c3c4c7; border-radius: 4px; margin: 10px 0;">
                <h3 style="margin-top: 0;">XML File Import</h3>
                <table class="form-table" style="margin: 0;">
                    <tr>
                        <th><label for="xml_file">WordPress Export XML</label></th>
                        <td>
                            <input type="file" id="xml_file" accept=".xml" style="padding: 10px; border: 2px dashed #2271b1; background: #fff; width: 100%; max-width: 400px;">
                            <p class="description">Upload a WordPress WXR export file containing tribe_events</p>
                        </td>
                    </tr>
                </table>
            </div>

            <h3>Import Settings</h3>
            <table class="form-table">
                <tr>
                    <th><label for="limit">Import Limit</label></th>
                    <td>
                        <input type="number" id="limit" value="50" min="0" style="width: 120px;">
                        <p class="description">Number of events to import (0 = all events)</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="per_page">Events Per Batch</label></th>
                    <td>
                        <input type="number" id="per_page" value="10" min="1" max="50">
                        <p class="description">Lower values (5-10) recommended to prevent timeouts</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="start_page">Start at Batch</label></th>
                    <td>
                        <input type="number" id="start_page" value="1" min="1">
                        <p class="description">Resume import from a specific batch (useful after cancellation)</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="date_from">Published Date Filter</label></th>
                    <td>
                        <label style="display: inline-block; margin-right: 20px;">
                            <strong>From:</strong> <input type="date" id="date_from" style="margin-left: 5px;">
                        </label>
                        <label style="display: inline-block;">
                            <strong>To:</strong> <input type="date" id="date_to" style="margin-left: 5px;">
                        </label>
                        <p class="description">Filter by published date - leave blank for no date restriction. Use 'From' only to import from a date onwards, 'To' only for up to a date, or both for a date range.</p>
                    </td>
                </tr>
                <tr>
                    <th>Import Options</th>
                    <td>
                        <label><input type="checkbox" id="only_new" value="1"> Only import NEW events (skip existing)</label><br>
                        <label><input type="checkbox" id="only_existing" value="1"> Only UPDATE existing events (skip new)</label><br>
                        <label><input type="checkbox" id="only_images" value="1"> Only UPDATE images (skip event data)</label><br>
                        <label><input type="checkbox" id="force_images" value="1"> Re-download images even if they exist</label><br>
                        <label><input type="checkbox" id="skip_images" value="1" checked> Skip image downloads entirely (faster)</label><br>
                        <label><input type="checkbox" id="debug_mode" value="1"> Enable debug mode (verbose logging)</label>
                    </td>
                </tr>
            </table>
            <p class="submit"><button type="button" id="start-import" class="button button-primary">Start Import</button></p>
        </div>

        <div id="fwk-import-progress" style="display:none;">
            <h2>Importing Events...</h2>
            <div class="fwk-stats">
                <h3>Import Configuration</h3>
                <ul>
                    <li><strong>Source:</strong> <span id="import-source">-</span></li>
                    <li><strong>Total Available:</strong> <span id="total-available">Checking...</span></li>
                    <li><strong>Will Process:</strong> <span id="will-process">-</span></li>
                    <li><strong>Mode:</strong> <span id="import-mode">-</span></li>
                    <li><strong>Images:</strong> <span id="image-mode">-</span></li>
                    <li><strong>Debug:</strong> <span id="debug-status">-</span></li>
                </ul>
            </div>
            <div class="fwk-progress-container">
                <div class="fwk-progress-bar" id="progress-bar" style="width: 0%;"></div>
                <div class="fwk-progress-text" id="progress-text">Initializing...</div>
            </div>
            <div class="fwk-stats">
                <h3>Live Statistics</h3>
                <ul>
                    <li id="stat-processed"><strong>Processed:</strong> 0</li>
                    <li id="stat-page"><strong>Current Batch:</strong> 1</li>
                    <li id="stat-created"><strong>Created:</strong> 0</li>
                    <li id="stat-updated"><strong>Updated:</strong> 0</li>
                    <li id="stat-skipped"><strong>Skipped:</strong> 0</li>
                    <li id="stat-duplicates"><strong>Duplicates Found:</strong> 0</li>
                    <li id="stat-venues"><strong>Venues Linked:</strong> 0</li>
                    <li id="stat-organizers"><strong>Organizers Linked:</strong> 0</li>
                    <li id="stat-images"><strong>Images:</strong> 0</li>
                    <li id="stat-errors"><strong>Errors:</strong> 0</li>
                    <li id="stat-memory"><strong>Memory:</strong> -</li>
                    <li id="stat-time"><strong>Batch Time:</strong> -</li>
                </ul>
            </div>
            <h3>Import Log</h3>
            <div class="fwk-log" id="import-log"></div>
            <p>
                <button type="button" id="cancel-import" class="button">Cancel Import</button>
                <button type="button" id="back-to-form" class="button" style="display:none;">Back to Form</button>
            </p>
        </div>

        <h2>How This Works:</h2>
        <ul>
            <li>Imports events or posts from API or WordPress XML export file</li>
            <li>Matches content by source ID (migration_id), slug, or title+date+content to avoid duplicates</li>
            <li><strong>Duplicate Detection:</strong> Content is duplicate if Title + Date + Content match (99%); 100% if migration_id also matches</li>
            <li><strong>Events only:</strong> Links venues and organizers using their migration_id</li>
            <li>Only fills in MISSING data - preserves existing destination content</li>
            <li>Downloads featured images only if missing (unless "force" is checked)</li>
        </ul>
        
        <h3>Troubleshooting:</h3>
        <ul>
            <li>Reduce "Events Per Batch" to 5-10 if you get timeouts</li>
            <li>Check "Skip image downloads" to test without images first</li>
            <li>Enable debug mode to see detailed processing info</li>
            <li>For events: Make sure venues and organizers are imported first with their migration_id set</li>
            <li><strong>NEW:</strong> Use the <strong>De-duplicate</strong> tab above to find and remove existing duplicates</li>
        </ul>
        </div>
        <!-- END IMPORT TAB -->

        <!-- DEDUPLICATE TAB -->
        <div id="deduplicate-tab" class="tab-content" style="display:none;">
        <?php fwk_deduplicate_content(); ?>
        </div>
        <!-- END DEDUPLICATE TAB -->

        <!-- BULK UPDATE TAB -->
        <div id="update-tab" class="tab-content" style="display:none;">
            <h2>Bulk Update Posts</h2>
            <p>Update multiple posts at once by status, post type, date range, or other criteria.</p>
            
            <div id="fwk-update-form">
                <h3>Update Settings</h3>
                <table class="form-table">
                    <tr>
                        <th><label for="update_post_type">Post Type</label></th>
                        <td>
                            <input type="text" id="update_post_type" class="regular-text" value="post" placeholder="post, page, events, etc.">
                            <p class="description">Post type to update</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="update_status">Post Status</label></th>
                        <td>
                            <select id="update_status">
                                <option value="any">Any Status</option>
                                <option value="publish">Published</option>
                                <option value="draft">Draft</option>
                                <option value="pending">Pending</option>
                                <option value="private">Private</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="update_limit">Limit</label></th>
                        <td>
                            <input type="number" id="update_limit" value="100" min="1" max="1000">
                            <p class="description">Maximum number of posts to update</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Update Actions</label></th>
                        <td>
                            <label><input type="checkbox" id="update_status_to" value="1"> Change status to: 
                                <select id="new_status">
                                    <option value="publish">Publish</option>
                                    <option value="draft">Draft</option>
                                    <option value="pending">Pending</option>
                                    <option value="private">Private</option>
                                    <option value="trash">Trash</option>
                                </select>
                            </label><br><br>
                            <label><input type="checkbox" id="update_author" value="1"> Change author to: 
                                <input type="number" id="new_author" value="1" min="1" style="width: 100px;"> (User ID)
                            </label><br><br>
                            <label><input type="checkbox" id="regenerate_excerpts" value="1"> Regenerate excerpts from content (first 150 words)</label><br>
                            <label><input type="checkbox" id="update_dates" value="1"> Update post dates to now</label>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Mode</label></th>
                        <td>
                            <label><input type="checkbox" id="update_dry_run" value="1" checked> <strong>Dry Run Mode</strong> (preview only, no changes)</label>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="button" id="start-bulk-update" class="button button-primary">Start Bulk Update</button>
                </p>
            </div>

            <div id="fwk-update-progress" style="display:none;">
                <h2>Updating Posts...</h2>
                <div class="fwk-progress-container">
                    <div class="fwk-progress-bar" id="update-progress-bar" style="width: 0%;"></div>
                    <div class="fwk-progress-text" id="update-progress-text">Processing...</div>
                </div>
                <div class="fwk-stats">
                    <h3>Update Statistics</h3>
                    <ul>
                        <li id="stat-update-processed"><strong>Processed:</strong> 0</li>
                        <li id="stat-update-updated"><strong>Updated:</strong> 0</li>
                        <li id="stat-update-errors"><strong>Errors:</strong> 0</li>
                    </ul>
                </div>
                <h3>Update Log</h3>
                <div class="fwk-log" id="update-log"></div>
                <p>
                    <button type="button" id="back-to-update-form" class="button">Back to Form</button>
                </p>
            </div>
        </div>
        <!-- END BULK UPDATE TAB -->

    </div>

    <style>
        .fwk-progress-container { background: #f0f0f0; border: 1px solid #ccc; border-radius: 5px; height: 30px; margin: 20px 0; position: relative; overflow: hidden; }
        .fwk-progress-bar { background: linear-gradient(90deg, #2271b1 0%, #135e96 100%); height: 100%; transition: width 0.3s ease; }
        .fwk-progress-text { position: absolute; width: 100%; text-align: center; line-height: 30px; font-weight: bold; color: #333; z-index: 2; }
        .fwk-stats { background: #fff; border: 1px solid #ccc; padding: 15px; margin: 20px 0; border-radius: 5px; }
        .fwk-stats ul { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; list-style: none; padding: 0; }
        .fwk-stats li { background: #f9f9f9; padding: 10px; border-radius: 3px; }
        .fwk-log { background: #fff; border: 1px solid #ccc; padding: 15px; max-height: 400px; overflow-y: auto; font-family: monospace; font-size: 12px; margin: 20px 0; }
        .fwk-log-item { margin: 5px 0; padding: 5px; border-left: 3px solid #ccc; }
        .fwk-log-created { border-left-color: #46b450; }
        .fwk-log-updated { border-left-color: #00a0d2; }
        .fwk-log-skipped { border-left-color: #ffb900; }
        .fwk-log-error { border-left-color: #dc3232; color: #dc3232; }
        .fwk-log-debug { border-left-color: #826eb4; color: #666; font-size: 11px; }
        .fwk-log-venue { border-left-color: #9b59b6; }
        .fwk-log-organizer { border-left-color: #3498db; }
        .fwk-log-duplicate { border-left-color: #ff6b6b; color: #c23030; font-weight: bold; }
    </style>

    <script>
    jQuery(document).ready(function($) {
        // Tab switching
        $('.nav-tab').click(function(e) {
            e.preventDefault();
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            $('.tab-content').hide();
            $($(this).attr('href')).show();
        });

        let importCancelled = false;
        let importStats = { processed: 0, created: 0, updated: 0, skipped: 0, images: 0, errors: 0, venues: 0, organizers: 0, duplicates: 0 };
        let retryCount = 0;
        let batchStartTime = 0;
        let xmlSessionId = '';

        $('input[name="import_method"]').change(function() {
            if ($(this).val() === 'api') {
                $('#api-options').show();
                $('#xml-options').hide();
            } else {
                $('#api-options').hide();
                $('#xml-options').show();
            }
        });

        $('#start-import').click(function() {
            importCancelled = false;
            importStats = { processed: 0, created: 0, updated: 0, skipped: 0, images: 0, errors: 0, venues: 0, organizers: 0, duplicates: 0 };

            const importMethod = $('input[name="import_method"]:checked').val();
            const postType    = $('#post_type').val();
            const limit       = parseInt($('#limit').val());
            const perPage     = parseInt($('#per_page').val());
            const startPage   = parseInt($('#start_page').val()) || 1;
            const dateFrom    = $('#date_from').val();
            const dateTo      = $('#date_to').val();
            const onlyNew     = $('#only_new').is(':checked');
            const onlyExisting= $('#only_existing').is(':checked');
            const onlyImages  = $('#only_images').is(':checked');
            const forceImages = $('#force_images').is(':checked');
            const skipImages  = $('#skip_images').is(':checked');
            const fuzzyMatch  = $('#fuzzy_match').is(':checked');
            const debugMode   = $('#debug_mode').is(':checked');

            $('#fwk-import-form').hide();
            $('#fwk-import-progress').show();
            $('#import-log').empty();
            $('#cancel-import').prop('disabled', false).text('Cancel Import').show();
            $('#back-to-form').hide();

            let mode = 'Create & Update';
            if (onlyNew) mode = 'New events only';
            if (onlyExisting) mode = 'Update existing only';
            if (onlyImages) mode = 'Images only';

            let imageMode = 'Download if missing';
            if (skipImages) imageMode = 'Skipped';
            else if (forceImages) imageMode = 'Force re-download';

            $('#import-mode').text(mode);
            $('#image-mode').text(imageMode);
            $('#debug-status').text(debugMode ? 'Enabled' : 'Disabled');

            if (importMethod === 'xml') {
                $('#import-source').text('XML File');
                const fileInput = $('#xml_file')[0];
                if (!fileInput.files || !fileInput.files[0]) {
                    addLog('Error: Please select an XML file', 'error');
                    $('#cancel-import').hide();
                    $('#back-to-form').show();
                    return;
                }

                addLog('Uploading and parsing XML file...', '');

                const formData = new FormData();
                formData.append('action', 'fwk_acf_upload_xml');
                formData.append('xml_file', fileInput.files[0]);
                formData.append('post_type', postType);

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    timeout: 120000
                }).done(function(response) {
                    if (!response.success) {
                        addLog('Error: ' + response.data, 'error');
                        $('#cancel-import').hide();
                        $('#back-to-form').show();
                        return;
                    }

                    xmlSessionId = response.data.session_id;
                    const totalAvailable = response.data.total;
                    const skippedEvents = (startPage - 1) * perPage;
                    const remainingEvents = Math.max(0, totalAvailable - skippedEvents);
                    const totalToProcess = (limit > 0) ? Math.min(limit, remainingEvents) : remainingEvents;

                    $('#total-available').text(totalAvailable + ' events');
                    $('#will-process').text((limit > 0 ? totalToProcess + ' events' : 'All remaining events') + (startPage > 1 ? ' (from batch ' + startPage + ')' : ''));
                    addLog('Parsed ' + totalAvailable + ' events from XML', 'created');
                    addLog('Will process ' + totalToProcess + ' events starting from batch ' + startPage, '');

                    setTimeout(function(){
                        importXmlBatch(xmlSessionId, startPage, perPage, totalToProcess, postType, forceImages, onlyNew, onlyExisting, onlyImages, fuzzyMatch, debugMode, skipImages, dateFrom, dateTo);
                    }, 200);
                }).fail(function(xhr, status, error) {
                    addLog('Upload error: ' + (error || status), 'error');
                    $('#cancel-import').hide();
                    $('#back-to-form').show();
                });

            } else {
                const sourceUrl = $('#source_url').val();
                $('#import-source').text('API: ' + sourceUrl.substring(0, 50) + '...');

                addLog('Fetching total event count...', '');
                if (startPage > 1) {
                    addLog('Starting from batch ' + startPage, '');
                }

                $.post(ajaxurl, {
                    action: 'fwk_acf_get_total_count',
                    source_url: sourceUrl,
                    per_page: perPage
                }, function(response) {
                    if (!response.success) {
                        addLog('Error: ' + response.data, 'error');
                        $('#cancel-import').hide();
                        $('#back-to-form').show();
                        return;
                    }

                    const totalAvailable = response.data.total;
                    const skippedEvents = (startPage - 1) * perPage;
                    const remainingEvents = Math.max(0, totalAvailable - skippedEvents);
                    const totalToProcess = (limit > 0) ? Math.min(limit, remainingEvents) : remainingEvents;

                    $('#total-available').text(totalAvailable + ' events');
                    $('#will-process').text((limit > 0 ? totalToProcess + ' events' : 'All remaining events') + (startPage > 1 ? ' (from batch ' + startPage + ')' : ''));
                    addLog('Found ' + totalAvailable + ' total events', '');
                    addLog('Will process ' + totalToProcess + ' events starting from batch ' + startPage, '');

                    setTimeout(function(){
                        importBatch(sourceUrl, startPage, perPage, totalToProcess, postType, forceImages, onlyNew, onlyExisting, onlyImages, fuzzyMatch, debugMode, skipImages, dateFrom, dateTo);
                    }, 200);
                });
            }
        });

        function importXmlBatch(sessionId, page, perPage, totalToProcess, postType, forceImages, onlyNew, onlyExisting, onlyImages, fuzzyMatch, debugMode, skipImages, dateFrom, dateTo) {
            if (importCancelled) {
                finishImport(page, 'CANCELLED');
                return;
            }

            const remaining  = totalToProcess > 0 ? (totalToProcess - importStats.processed) : perPage;
            const fetchCount = Math.max(0, Math.min(perPage, remaining));

            if (fetchCount <= 0) {
                finishImport(page, 'COMPLETE');
                return;
            }

            batchStartTime = Date.now();
            addLog('Processing batch ' + page + ' (' + fetchCount + ' events)...', 'debug');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                timeout: 180000,
                data: {
                    action: 'fwk_acf_import_xml_batch',
                    session_id: sessionId,
                    page: page,
                    per_page: fetchCount,
                    post_type: postType,
                    date_from: dateFrom,
                    date_to: dateTo,
                    force_images: forceImages ? 1 : 0,
                    skip_images: skipImages ? 1 : 0,
                    only_new: onlyNew ? 1 : 0,
                    only_existing: onlyExisting ? 1 : 0,
                    only_images: onlyImages ? 1 : 0,
                    fuzzy_match: fuzzyMatch ? 1 : 0,
                    debug_mode: debugMode ? 1 : 0
                }
            }).done(function(response) {
                retryCount = 0;
                const batchTime = ((Date.now() - batchStartTime) / 1000).toFixed(1);
                
                if (!response.success) {
                    addLog('Batch error: ' + response.data, 'error');
                    importStats.errors++;
                    updateStats(page, batchTime, null);
                    setTimeout(function(){
                        importXmlBatch(sessionId, page + 1, perPage, totalToProcess, postType, forceImages, onlyNew, onlyExisting, onlyImages, fuzzyMatch, debugMode, skipImages, dateFrom, dateTo);
                    }, 1000);
                    return;
                }

                processResponse(response.data, page, batchTime);

                if (response.data.has_more && (totalToProcess === 0 || importStats.processed < totalToProcess)) {
                    let delay = 500;
                    if (parseFloat(batchTime) > 30) delay = 2000;
                    else if (parseFloat(batchTime) > 15) delay = 1000;

                    setTimeout(function(){
                        importXmlBatch(sessionId, page + 1, perPage, totalToProcess, postType, forceImages, onlyNew, onlyExisting, onlyImages, fuzzyMatch, debugMode, skipImages, dateFrom, dateTo);
                    }, delay);
                } else {
                    finishImport(page, 'COMPLETE');
                }
            }).fail(function(xhr, status, error) {
                handleBatchError(xhr, status, error, page, function() {
                    importXmlBatch(sessionId, page, perPage, totalToProcess, postType, forceImages, onlyNew, onlyExisting, onlyImages, fuzzyMatch, debugMode, skipImages, dateFrom, dateTo);
                }, function() {
                    importXmlBatch(sessionId, page + 1, perPage, totalToProcess, postType, forceImages, onlyNew, onlyExisting, onlyImages, fuzzyMatch, debugMode, skipImages, dateFrom, dateTo);
                });
            });
        }

        function importBatch(sourceUrl, page, perPage, totalToProcess, postType, forceImages, onlyNew, onlyExisting, onlyImages, fuzzyMatch, debugMode, skipImages, dateFrom, dateTo) {
            if (importCancelled) {
                finishImport(page, 'CANCELLED');
                return;
            }

            const remaining  = totalToProcess > 0 ? (totalToProcess - importStats.processed) : perPage;
            const fetchCount = Math.max(0, Math.min(perPage, remaining));

            if (fetchCount <= 0) {
                finishImport(page, 'COMPLETE');
                return;
            }

            batchStartTime = Date.now();
            addLog('Starting batch ' + page + ' (' + fetchCount + ' events)...', 'debug');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                timeout: 180000,
                data: {
                    action: 'fwk_acf_import_batch',
                    source_url: sourceUrl,
                    page: page,
                    per_page: fetchCount,
                    post_type: postType,
                    date_from: dateFrom,
                    date_to: dateTo,
                    force_images: forceImages ? 1 : 0,
                    skip_images: skipImages ? 1 : 0,
                    only_new: onlyNew ? 1 : 0,
                    only_existing: onlyExisting ? 1 : 0,
                    only_images: onlyImages ? 1 : 0,
                    fuzzy_match: fuzzyMatch ? 1 : 0,
                    debug_mode: debugMode ? 1 : 0
                }
            }).done(function(response) {
                retryCount = 0;
                const batchTime = ((Date.now() - batchStartTime) / 1000).toFixed(1);
                
                if (!response.success) {
                    addLog('Batch error: ' + response.data, 'error');
                    importStats.errors++;
                    updateStats(page, batchTime, response.data ? response.data.memory_usage : null);
                    setTimeout(function(){
                        importBatch(sourceUrl, page + 1, perPage, totalToProcess, postType, forceImages, onlyNew, onlyExisting, onlyImages, fuzzyMatch, debugMode, skipImages, dateFrom, dateTo);
                    }, 2000);
                    return;
                }

                processResponse(response.data, page, batchTime);

                if (response.data.has_more && (totalToProcess === 0 || importStats.processed < totalToProcess)) {
                    let delay = 1000;
                    if (parseFloat(batchTime) > 30) delay = 3000;
                    else if (parseFloat(batchTime) > 15) delay = 2000;
                    
                    addLog('Batch ' + page + ' completed in ' + batchTime + 's. Next batch in ' + (delay/1000) + 's...', 'debug');

                    setTimeout(function(){
                        importBatch(sourceUrl, page + 1, perPage, totalToProcess, postType, forceImages, onlyNew, onlyExisting, onlyImages, fuzzyMatch, debugMode, skipImages, dateFrom, dateTo);
                    }, delay);
                } else {
                    finishImport(page, 'COMPLETE');
                }
            }).fail(function(xhr, status, error) {
                handleBatchError(xhr, status, error, page, function() {
                    importBatch(sourceUrl, page, perPage, totalToProcess, postType, forceImages, onlyNew, onlyExisting, onlyImages, fuzzyMatch, debugMode, skipImages, dateFrom, dateTo);
                }, function() {
                    importBatch(sourceUrl, page + 1, perPage, totalToProcess, postType, forceImages, onlyNew, onlyExisting, onlyImages, fuzzyMatch, debugMode, skipImages, dateFrom, dateTo);
                });
            });
        }

        function processResponse(data, page, batchTime) {
            importStats.processed += data.processed;
            importStats.created   += data.created;
            importStats.updated   += data.updated;
            importStats.skipped   += data.skipped;
            importStats.images    += data.images_downloaded;
            importStats.errors    += data.errors.length;
            importStats.venues    += data.venues_linked || 0;
            importStats.organizers += data.organizers_linked || 0;
            importStats.duplicates += data.duplicates_found || 0;

            data.logs.forEach(function(log) {
                addLog(log.message, log.type);
            });

            updateStats(page, batchTime, data.memory_usage);
            updateProgress(importStats.processed, parseInt($('#will-process').text()) || importStats.processed);
        }

        function handleBatchError(xhr, status, error, page, retryCallback, skipCallback) {
            const batchTime = ((Date.now() - batchStartTime) / 1000).toFixed(1);
            let errMsg = error || status || 'Unknown error';
            
            if (xhr.responseText) {
                try {
                    const jsonResponse = JSON.parse(xhr.responseText);
                    if (jsonResponse.data) errMsg = jsonResponse.data;
                } catch(e) {
                    if (xhr.responseText.includes('Fatal error')) errMsg = 'PHP Fatal Error';
                    else if (xhr.responseText.includes('memory')) errMsg = 'Memory limit exceeded';
                    else if (xhr.responseText.length < 500) errMsg = xhr.responseText;
                }
            }
            
            if (xhr.status) errMsg = 'HTTP ' + xhr.status + ': ' + errMsg;
            if (status === 'timeout') errMsg = 'Request timed out - reduce batch size';
            
            addLog('Error on batch ' + page + ': ' + errMsg, 'error');
            importStats.errors++;
            updateStats(page, batchTime, null);

            retryCount++;
            if (retryCount <= 3) {
                const retryDelay = 3000 * retryCount;
                addLog('Retrying batch ' + page + ' in ' + (retryDelay/1000) + 's...', 'error');
                setTimeout(retryCallback, retryDelay);
            } else {
                addLog('Skipping batch ' + page + ' after 3 retries', 'error');
                retryCount = 0;
                setTimeout(skipCallback, 2000);
            }
        }

        function finishImport(page, status) {
            addLog('════════════════════════════════════════', '');
            addLog('IMPORT ' + status + '!', status === 'COMPLETE' ? 'created' : 'error');
            if (status === 'CANCELLED') {
                addLog('To resume, set "Start at Batch" to ' + page, '');
            }
            addLog('Total Processed: ' + importStats.processed, '');
            addLog('Created: ' + importStats.created, 'created');
            addLog('Updated: ' + importStats.updated, 'updated');
            addLog('Skipped: ' + importStats.skipped, 'skipped');
            addLog('Duplicates Detected: ' + importStats.duplicates, 'duplicate');
            addLog('Venues Linked: ' + importStats.venues, 'venue');
            addLog('Organizers Linked: ' + importStats.organizers, 'organizer');
            addLog('Images Downloaded: ' + importStats.images, 'created');
            addLog('Errors: ' + importStats.errors, 'error');
            $('#cancel-import').hide();
            $('#back-to-form').show();
        }

        function updateProgress(processed, total) {
            const percent = total ? Math.round((processed / total) * 100) : 0;
            $('#progress-bar').css('width', percent + '%');
            $('#progress-text').text(percent + '% (' + processed + '/' + (total || '∞') + ')');
        }

        function updateStats(page, batchTime, memoryUsage) {
            $('#stat-processed').html('<strong>Processed:</strong> ' + importStats.processed);
            $('#stat-page').html('<strong>Current Batch:</strong> ' + (page || 1));
            $('#stat-created').html('<strong>Created:</strong> ' + importStats.created);
            $('#stat-updated').html('<strong>Updated:</strong> ' + importStats.updated);
            $('#stat-skipped').html('<strong>Skipped:</strong> ' + importStats.skipped);
            $('#stat-duplicates').html('<strong>Duplicates Found:</strong> ' + importStats.duplicates);
            $('#stat-venues').html('<strong>Venues Linked:</strong> ' + importStats.venues);
            $('#stat-organizers').html('<strong>Organizers Linked:</strong> ' + importStats.organizers);
            $('#stat-images').html('<strong>Images:</strong> ' + importStats.images);
            $('#stat-errors').html('<strong>Errors:</strong> ' + importStats.errors);
            if (batchTime) $('#stat-time').html('<strong>Batch Time:</strong> ' + batchTime + 's');
            if (memoryUsage) $('#stat-memory').html('<strong>Memory:</strong> ' + memoryUsage);
        }

        function addLog(message, type) {
            const $log = $('<div class="fwk-log-item fwk-log-' + (type || '') + '"></div>').text(message);
            $('#import-log').append($log);
            $('#import-log').scrollTop($('#import-log')[0].scrollHeight);
        }

        $('#cancel-import').click(function() {
            importCancelled = true;
            $(this).prop('disabled', true).text('Cancelling...');
        });

        $('#back-to-form').click(function() {
            $('#fwk-import-progress').hide();
            $('#fwk-import-form').show();
        });

        // ========================================
        // BULK UPDATE TAB
        // ========================================
        
        $('#start-bulk-update').click(function() {
            const postType = $('#update_post_type').val();
            const status = $('#update_status').val();
            const limit = parseInt($('#update_limit').val());
            const dryRun = $('#update_dry_run').is(':checked');
            
            const actions = {
                change_status: $('#update_status_to').is(':checked') ? $('#new_status').val() : null,
                change_author: $('#update_author').is(':checked') ? $('#new_author').val() : null,
                regenerate_excerpts: $('#regenerate_excerpts').is(':checked'),
                update_dates: $('#update_dates').is(':checked')
            };
            
            if (!actions.change_status && !actions.change_author && !actions.regenerate_excerpts && !actions.update_dates) {
                alert('Please select at least one update action!');
                return;
            }
            
            if (!dryRun && !confirm('Are you sure you want to update these posts? This cannot be undone!')) {
                return;
            }
            
            $('#fwk-update-form').hide();
            $('#fwk-update-progress').show();
            $('#update-log').empty();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'fwk_bulk_update_posts',
                    post_type: postType,
                    status: status,
                    limit: limit,
                    actions: JSON.stringify(actions),
                    dry_run: dryRun ? 1 : 0
                },
                timeout: 120000
            }).done(function(response) {
                if (!response.success) {
                    addUpdateLog('Error: ' + response.data, 'error');
                    return;
                }
                
                const data = response.data;
                $('#stat-update-processed').html('<strong>Processed:</strong> ' + data.processed);
                $('#stat-update-updated').html('<strong>Updated:</strong> ' + data.updated);
                $('#stat-update-errors').html('<strong>Errors:</strong> ' + data.errors);
                
                const percent = 100;
                $('#update-progress-bar').css('width', percent + '%');
                $('#update-progress-text').text('Complete!');
                
                data.logs.forEach(function(log) {
                    addUpdateLog(log.message, log.type);
                });
                
                addUpdateLog('═══════════════════════════════════', '');
                addUpdateLog('BULK UPDATE COMPLETE!', 'created');
                if (dryRun) {
                    addUpdateLog('THIS WAS A DRY RUN - No changes were made', 'debug');
                }
            }).fail(function(xhr, status, error) {
                addUpdateLog('Update failed: ' + (error || status), 'error');
            });
        });
        
        function addUpdateLog(message, type) {
            const $log = $('<div class="fwk-log-item fwk-log-' + (type || '') + '"></div>').text(message);
            $('#update-log').append($log);
            $('#update-log').scrollTop($('#update-log')[0].scrollHeight);
        }
        
        $('#back-to-update-form').click(function() {
            $('#fwk-update-progress').hide();
            $('#fwk-update-form').show();
        });
    });
    </script>
    <?php
}

// ============================================================================
// DE-DUPLICATOR PAGE
// ============================================================================

function fwk_deduplicate_content() {
    ?>
        <h2>De-duplicate Content</h2>
        <p><strong>Choose which fields must match to identify duplicates.</strong> Posts matching your selected criteria will be grouped together.</p>
        
        <div id="fwk-dedup-form">
            <h3>Scan Settings</h3>
            <table class="form-table">
                <tr>
                    <th><label for="dedup_post_type">Post Type</label></th>
                    <td>
                        <input type="text" id="dedup_post_type" class="regular-text" value="post" placeholder="post, page, events, etc.">
                        <p class="description">Enter post type to scan for duplicates (e.g., post, page, events, tribe_events, custom_post_type)</p>
                    </td>
                </tr>
                <tr>
                    <th><label>Match Criteria</label></th>
                    <td>
                        <p style="margin-bottom: 10px;"><strong>Posts must match ALL selected fields to be considered duplicates:</strong></p>
                        <label style="display: block; margin: 8px 0;">
                            <input type="checkbox" id="match_title" value="1" checked>
                            <strong>Title</strong> - Post titles must be identical
                        </label>
                        <label style="display: block; margin: 8px 0;">
                            <input type="checkbox" id="match_date" value="1" checked>
                            <strong>Post Date</strong> - Publication dates must match
                        </label>
                        <label style="display: block; margin: 8px 0;">
                            <input type="checkbox" id="match_content" value="1" checked>
                            <strong>Content</strong> - Post content must be identical
                        </label>
                        <label style="display: block; margin: 8px 0;">
                            <input type="checkbox" id="match_migration_id" value="1">
                            <strong>Migration ID</strong> - Custom migration_id meta field must match (for imported content)
                        </label>
                        <label style="display: block; margin: 8px 0;">
                            <input type="checkbox" id="match_excerpt" value="1">
                            <strong>Excerpt</strong> - Post excerpts must be identical
                        </label>
                        <p class="description">Select at least one matching criterion. More criteria = stricter duplicate detection.</p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="button" id="start-scan" class="button button-primary button-large">Scan for Duplicates</button>
            </p>
        </div>

        <!-- PROGRESS SECTION -->
        <div id="fwk-dedup-progress" style="display:none;">
            <h2 id="progress-title">Scanning for Duplicates...</h2>
            <div class="fwk-progress-container">
                <div class="fwk-progress-bar" id="dedup-progress-bar" style="width: 0%;"></div>
                <div class="fwk-progress-text" id="dedup-progress-text">Starting scan...</div>
            </div>
            <div class="fwk-stats" id="dedup-stats" style="display:none;">
                <h3>Scan Statistics</h3>
                <ul>
                    <li id="stat-dedup-scanned"><strong>Posts Scanned:</strong> 0</li>
                    <li id="stat-dedup-groups"><strong>Duplicate Groups:</strong> 0</li>
                    <li id="stat-dedup-total"><strong>Total Duplicates:</strong> 0</li>
                </ul>
            </div>
            <h3 id="dedup-log-title" style="display:none;">Scan Log</h3>
            <div class="fwk-log" id="dedup-log" style="display:none;"></div>
        </div>

        <div id="fwk-dedup-results" style="display:none;">
            <h2>Duplicate Groups Found</h2>
            <p id="duplicate-count" style="font-size: 16px; font-weight: bold;"></p>
            
            <div id="bulk-actions-panel" style="background: #f0f6fc; padding: 15px; border: 1px solid #2271b1; border-radius: 4px; margin: 20px 0;">
                <h3 style="margin-top: 0;">Bulk Processing Options</h3>
                <table class="form-table" style="margin: 0;">
                    <tr>
                        <th><label for="batch_size">Process Per Batch</label></th>
                        <td>
                            <input type="number" id="batch_size" value="5" min="1" max="20" style="width: 80px;">
                            <p class="description">Number of duplicate groups to process at once</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="batch_action">Batch Action</label></th>
                        <td>
                            <select id="batch_action">
                                <option value="merge">Merge duplicates (keep first in each group)</option>
                                <option value="delete">Delete duplicates (keep first in each group)</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Processing Mode</label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="dry_run" value="1" checked>
                                <strong>Dry Run Mode</strong> - Preview changes without making them
                            </label>
                            <p class="description">Uncheck to actually process duplicates. Dry run shows what would happen.</p>
                        </td>
                    </tr>
                </table>
                <p class="submit" style="margin: 15px 0 0 0; padding: 0;">
                    <button type="button" id="process-all-batches" class="button button-primary button-large">Process All Duplicate Groups</button>
                </p>
            </div>

            <div id="duplicate-groups"></div>
            <p>
                <button type="button" id="back-to-scan" class="button">Back to Scan</button>
            </p>
        </div>

        <div id="fwk-dedup-progress" style="display:none;">
            <h2 id="progress-title">Scanning for duplicates...</h2>
            <div class="fwk-progress-container">
                <div class="fwk-progress-bar" id="dedup-progress-bar" style="width: 0%;"></div>
                <div class="fwk-progress-text" id="dedup-progress-text">Scanning...</div>
            </div>
            <div id="dedup-stats" style="display:none;" class="fwk-stats">
                <h3>Processing Statistics</h3>
                <ul>
                    <li id="stat-dedup-processed"><strong>Groups Processed:</strong> 0</li>
                    <li id="stat-dedup-merged"><strong>Events Merged:</strong> 0</li>
                    <li id="stat-dedup-deleted"><strong>Events Deleted:</strong> 0</li>
                    <li id="stat-dedup-errors"><strong>Errors:</strong> 0</li>
                </ul>
            </div>
            <h3 id="dedup-log-title" style="display:none;">Processing Log</h3>
            <div class="fwk-log" id="dedup-log" style="display:none;"></div>
            <p id="dedup-cancel-container" style="display:none;">
                <button type="button" id="cancel-dedup" class="button">Cancel Processing</button>
            </p>
        </div>
    </div>

    <style>
        .duplicate-group {
            background: #fff;
            border: 2px solid #dc3232;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .duplicate-group h3 {
            margin-top: 0;
            color: #dc3232;
        }
        .duplicate-item {
            background: #f9f9f9;
            border-left: 4px solid #0073aa;
            padding: 10px;
            margin: 10px 0;
        }
        .duplicate-item.keeper {
            border-left-color: #46b450;
            background: #f0f9f0;
        }
        .duplicate-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .duplicate-item-title {
            font-weight: bold;
            font-size: 14px;
        }
        .duplicate-item-meta {
            font-size: 12px;
            color: #666;
        }
        .duplicate-actions {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
        }
        .similarity-badge {
            display: inline-block;
            background: #ff6b6b;
            color: white;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
        }
    </style>

    <script>
    jQuery(document).ready(function($) {
        let duplicateGroups = [];
        let processingCancelled = false;
        let dedupStats = { processed: 0, merged: 0, deleted: 0, errors: 0 };

        $('#start-scan').click(function() {
            // Validate match criteria
            const matchTitle = $('#match_title').is(':checked');
            const matchDate = $('#match_date').is(':checked');
            const matchContent = $('#match_content').is(':checked');
            const matchMigrationId = $('#match_migration_id').is(':checked');
            const matchExcerpt = $('#match_excerpt').is(':checked');
            
            if (!matchTitle && !matchDate && !matchContent && !matchMigrationId && !matchExcerpt) {
                alert('Please select at least one matching criterion!');
                return;
            }
            
            const postType = $('#dedup_post_type').val().trim();
            if (!postType) {
                alert('Please enter a post type!');
                return;
            }
            
            // Show progress section
            $('#fwk-dedup-form').hide();
            $('#fwk-dedup-results').hide();
            $('#fwk-dedup-progress').show();
            $('#progress-title').text('Scanning for duplicates...');
            $('#dedup-stats').show();
            $('#dedup-log').show().empty();
            $('#dedup-log-title').show();
            
            // Reset progress
            $('#dedup-progress-bar').css('width', '0%');
            $('#dedup-progress-text').text('Starting scan...');
            $('#stat-dedup-scanned').html('<strong>Posts Scanned:</strong> 0');
            $('#stat-dedup-groups').html('<strong>Duplicate Groups:</strong> 0');
            $('#stat-dedup-total').html('<strong>Total Duplicates:</strong> 0');
            
            // Log start
            addDedupLog('Starting duplicate scan for post type: ' + postType, 'created');
            
            const criteria = [];
            if (matchTitle) criteria.push('Title');
            if (matchDate) criteria.push('Date');
            if (matchContent) criteria.push('Content');
            if (matchMigrationId) criteria.push('Migration ID');
            if (matchExcerpt) criteria.push('Excerpt');
            
            addDedupLog('Match criteria: ' + criteria.join(' + '), 'debug');
            addDedupLog('Fetching posts from database...', '');
            
            $('#dedup-progress-bar').css('width', '10%');
            $('#dedup-progress-text').text('Fetching posts...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'fwk_scan_duplicates',
                    post_type: postType,
                    match_title: matchTitle ? 1 : 0,
                    match_date: matchDate ? 1 : 0,
                    match_content: matchContent ? 1 : 0,
                    match_migration_id: matchMigrationId ? 1 : 0,
                    match_excerpt: matchExcerpt ? 1 : 0
                },
                timeout: 120000
            }).done(function(response) {
                $('#dedup-progress-bar').css('width', '100%');
                $('#dedup-progress-text').text('Scan complete!');
                
                if (!response.success) {
                    addDedupLog('Error: ' + response.data, 'error');
                    setTimeout(function() {
                        $('#fwk-dedup-progress').hide();
                        $('#fwk-dedup-form').show();
                    }, 2000);
                    return;
                }

                const data = response.data;
                
                // Update stats
                $('#stat-dedup-scanned').html('<strong>Posts Scanned:</strong> ' + (data.total_posts || 0));
                $('#stat-dedup-groups').html('<strong>Duplicate Groups:</strong> ' + (data.groups?.length || 0));
                $('#stat-dedup-total').html('<strong>Total Duplicates:</strong> ' + data.total_duplicates);
                
                // Log results
                addDedupLog('═══════════════════════════════════', '');
                addDedupLog('Scan Complete!', 'created');
                addDedupLog('Posts scanned: ' + (data.total_posts || 0), '');
                addDedupLog('Duplicate groups found: ' + (data.groups?.length || 0), '');
                addDedupLog('Total duplicates: ' + data.total_duplicates, '');
                
                if (data.groups && data.groups.length > 0) {
                    addDedupLog('Displaying results...', '');
                    
                    setTimeout(function() {
                        duplicateGroups = data.groups;
                        displayDuplicates(data);
                        $('#fwk-dedup-progress').hide();
                        $('#fwk-dedup-results').show();
                    }, 1000);
                } else {
                    addDedupLog('No duplicates found!', 'created');
                    setTimeout(function() {
                        $('#fwk-dedup-progress').hide();
                        $('#fwk-dedup-form').show();
                    }, 2000);
                }
            }).fail(function(xhr, status, error) {
                $('#dedup-progress-bar').css('width', '100%');
                $('#dedup-progress-text').text('Error!');
                addDedupLog('Scan failed: ' + (error || status), 'error');
                setTimeout(function() {
                    $('#fwk-dedup-progress').hide();
                    $('#fwk-dedup-form').show();
                }, 3000);
            });
        });
        
        function addDedupLog(message, type) {
            const $log = $('<div class="fwk-log-item fwk-log-' + (type || '') + '"></div>').text(message);
            $('#dedup-log').append($log);
            $('#dedup-log').scrollTop($('#dedup-log')[0].scrollHeight);
        }

        $('#process-all-batches').click(function() {
            const batchSize = parseInt($('#batch_size').val());
            const action = $('#batch_action').val();
            const dryRun = $('#dry_run').is(':checked');

            if (duplicateGroups.length === 0) {
                alert('No duplicate groups to process');
                return;
            }

            const actionText = action === 'merge' ? 'merge' : 'delete';
            const modeText = dryRun ? ' (DRY RUN - no changes will be made)' : '';
            
            if (!dryRun) {
                if (!confirm('Are you sure you want to ' + actionText + ' ' + duplicateGroups.length + ' duplicate groups? This cannot be undone!')) {
                    return;
                }
            }

            processingCancelled = false;
            dedupStats = { processed: 0, merged: 0, deleted: 0, errors: 0 };

            $('#fwk-dedup-results').hide();
            $('#fwk-dedup-progress').show();
            $('#progress-title').text((dryRun ? 'DRY RUN: Previewing ' : 'Processing ') + actionText + ' operation...');
            $('#dedup-stats').show();
            $('#dedup-log').show().empty();
            $('#dedup-log-title').show();
            $('#dedup-cancel-container').show();

            addDedupLog('════════════════════════════════════════', '');
            addDedupLog((dryRun ? 'DRY RUN MODE - No changes will be made' : 'LIVE MODE - Changes will be applied'), dryRun ? 'debug' : 'error');
            addDedupLog('Action: ' + actionText.toUpperCase(), '');
            addDedupLog('Total groups: ' + duplicateGroups.length, '');
            addDedupLog('Batch size: ' + batchSize, '');
            addDedupLog('════════════════════════════════════════', '');

            processBatch(0, batchSize, action, dryRun);
        });

        function processBatch(startIndex, batchSize, action, dryRun) {
            if (processingCancelled) {
                finishDedup('CANCELLED');
                return;
            }

            if (startIndex >= duplicateGroups.length) {
                finishDedup('COMPLETE');
                return;
            }

            const endIndex = Math.min(startIndex + batchSize, duplicateGroups.length);
            const batchGroups = duplicateGroups.slice(startIndex, endIndex);

            updateDedupProgress(startIndex, duplicateGroups.length);
            addDedupLog('Processing batch: groups ' + (startIndex + 1) + '-' + endIndex + '...', 'debug');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'fwk_process_duplicate_batch',
                    groups: JSON.stringify(batchGroups),
                    batch_action: action,
                    dry_run: dryRun ? 1 : 0
                },
                timeout: 120000
            }).done(function(response) {
                if (!response.success) {
                    addDedupLog('Batch error: ' + response.data, 'error');
                    dedupStats.errors++;
                } else {
                    const data = response.data;
                    dedupStats.processed += data.processed;
                    dedupStats.merged += data.merged;
                    dedupStats.deleted += data.deleted;
                    dedupStats.errors += data.errors;

                    data.logs.forEach(function(log) {
                        addDedupLog(log.message, log.type);
                    });
                }

                updateDedupStats();

                // Continue with next batch
                setTimeout(function() {
                    processBatch(endIndex, batchSize, action, dryRun);
                }, 500);
            }).fail(function(xhr, status, error) {
                addDedupLog('Batch failed: ' + (error || status), 'error');
                dedupStats.errors++;
                updateDedupStats();

                // Continue with next batch anyway
                setTimeout(function() {
                    processBatch(endIndex, batchSize, action, dryRun);
                }, 2000);
            });
        }

        function finishDedup(status) {
            const isDryRun = $('#dry_run').is(':checked');
            addDedupLog('════════════════════════════════════════', '');
            addDedupLog('PROCESSING ' + status + '!', status === 'COMPLETE' ? 'created' : 'error');
            addDedupLog('Groups Processed: ' + dedupStats.processed, '');
            if (isDryRun) {
                addDedupLog('Events that WOULD BE merged: ' + dedupStats.merged, 'debug');
                addDedupLog('Events that WOULD BE deleted: ' + dedupStats.deleted, 'debug');
                addDedupLog('', '');
                addDedupLog('THIS WAS A DRY RUN - No changes were made', 'created');
                addDedupLog('Uncheck "Dry Run Mode" to apply changes', '');
            } else {
                addDedupLog('Events Merged: ' + dedupStats.merged, 'created');
                addDedupLog('Events Deleted: ' + dedupStats.deleted, 'updated');
            }
            addDedupLog('Errors: ' + dedupStats.errors, 'error');
            $('#dedup-cancel-container').hide();
            updateDedupProgress(duplicateGroups.length, duplicateGroups.length);

            if (!isDryRun && status === 'COMPLETE') {
                setTimeout(function() {
                    if (confirm('Processing complete! Reload the page to see updated results?')) {
                        location.reload();
                    }
                }, 1000);
            }
        }

        function updateDedupProgress(current, total) {
            const percent = total ? Math.round((current / total) * 100) : 0;
            $('#dedup-progress-bar').css('width', percent + '%');
            $('#dedup-progress-text').text(percent + '% (' + current + '/' + total + ' groups)');
        }

        function updateDedupStats() {
            $('#stat-dedup-processed').html('<strong>Groups Processed:</strong> ' + dedupStats.processed);
            $('#stat-dedup-merged').html('<strong>Events Merged:</strong> ' + dedupStats.merged);
            $('#stat-dedup-deleted').html('<strong>Events Deleted:</strong> ' + dedupStats.deleted);
            $('#stat-dedup-errors').html('<strong>Errors:</strong> ' + dedupStats.errors);
        }

        function addDedupLog(message, type) {
            const $log = $('<div class="fwk-log-item fwk-log-' + (type || '') + '"></div>').text(message);
            $('#dedup-log').append($log);
            $('#dedup-log').scrollTop($('#dedup-log')[0].scrollHeight);
        }

        $('#cancel-dedup').click(function() {
            processingCancelled = true;
            $(this).prop('disabled', true).text('Cancelling...');
        });

        function displayDuplicates(data) {
            const groups = data.groups;
            const count = groups.length;
            const scanDryRun = data.dry_run;

            let countText = 'Found ' + count + ' duplicate group(s) with ' + data.total_duplicates + ' duplicate events';
            if (scanDryRun) {
                countText += ' (Scanned in Dry Run Mode)';
            }
            $('#duplicate-count').text(countText);

            const $container = $('#duplicate-groups');
            $container.empty();

            if (count === 0) {
                $container.html('<p style="color: #46b450; font-weight: bold;">✓ No duplicates found! Your events are clean.</p>');
                $('#bulk-actions-panel').hide();
                return;
            }

            $('#bulk-actions-panel').show();

            groups.forEach(function(group, index) {
                const $group = $('<div class="duplicate-group"></div>');
                $group.append('<h3>Duplicate Group #' + (index + 1) + ' <span class="similarity-badge">' + Math.round(group.similarity) + '% similar</span></h3>');
                $group.append('<p><strong>Match Type:</strong> ' + group.match_type + '</p>');

                group.events.forEach(function(event, eventIndex) {
                    const isKeeper = eventIndex === 0;
                    const $item = $('<div class="duplicate-item ' + (isKeeper ? 'keeper' : '') + '"></div>');
                    
                    $item.append(
                        '<div class="duplicate-item-header">' +
                            '<div>' +
                                '<div class="duplicate-item-title">' + 
                                    (isKeeper ? '✓ KEEP: ' : '× ') + 
                                    event.title + 
                                '</div>' +
                                '<div class="duplicate-item-meta">ID: ' + event.ID + ' | ' + 
                                'Date: ' + event.date + ' | ' +
                                'Status: ' + event.status + ' | ' +
                                (event.migration_id ? 'Migration ID: ' + event.migration_id : 'No Migration ID') +
                                '</div>' +
                            '</div>' +
                            '<div><a href="' + event.edit_link + '" target="_blank" class="button button-small">View</a></div>' +
                        '</div>'
                    );

                    if (!isKeeper) {
                        $item.append(
                            '<div class="duplicate-actions">' +
                                '<button class="button button-small merge-duplicate" data-from="' + event.ID + '" data-to="' + group.events[0].ID + '">Merge into #' + group.events[0].ID + '</button> ' +
                                '<button class="button button-small button-link-delete delete-duplicate" data-id="' + event.ID + '">Delete This</button>' +
                            '</div>'
                        );
                    }

                    $group.append($item);
                });

                $container.append($group);
            });

            // Bind merge action (individual)
            $('.merge-duplicate').click(function() {
                const fromId = $(this).data('from');
                const toId = $(this).data('to');
                
                if (!confirm('Merge event #' + fromId + ' into event #' + toId + '? This will delete event #' + fromId + ' after copying missing data.')) {
                    return;
                }

                $(this).prop('disabled', true).text('Merging...');

                $.post(ajaxurl, {
                    action: 'fwk_merge_duplicate',
                    from_id: fromId,
                    to_id: toId,
                    dry_run: 0
                }, function(response) {
                    if (response.success) {
                        alert('Merged successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                    }
                });
            });

            // Bind delete action (individual)
            $('.delete-duplicate').click(function() {
                const eventId = $(this).data('id');
                
                if (!confirm('Permanently delete event #' + eventId + '? This cannot be undone!')) {
                    return;
                }

                $(this).prop('disabled', true).text('Deleting...');

                $.post(ajaxurl, {
                    action: 'fwk_delete_duplicate',
                    event_id: eventId
                }, function(response) {
                    if (response.success) {
                        alert('Deleted successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                    }
                });
            });
        }

        $('#back-to-scan').click(function() {
            $('#fwk-dedup-results').hide();
            $('#fwk-dedup-form').show();
        });
    });
    </script>
    <?php
}

// ============================================================================
// AJAX HANDLERS
// ============================================================================

function fwk_ajax_scan_duplicates() {
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    @set_time_limit(120);
    @ini_set('memory_limit', '512M');

    $post_type = sanitize_text_field($_POST['post_type'] ?? 'post');
    
    // Get match criteria from frontend
    $match_title = !empty($_POST['match_title']);
    $match_date = !empty($_POST['match_date']);
    $match_content = !empty($_POST['match_content']);
    $match_migration_id = !empty($_POST['match_migration_id']);
    $match_excerpt = !empty($_POST['match_excerpt']);
    
    // Validate at least one criterion
    if (!$match_title && !$match_date && !$match_content && !$match_migration_id && !$match_excerpt) {
        wp_send_json_error('At least one matching criterion must be selected');
    }

    $args = [
        'post_type' => $post_type,
        'post_status' => 'any',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'orderby' => 'title',
        'order' => 'ASC'
    ];

    $post_ids = get_posts($args);
    $total_posts = count($post_ids);

    if (empty($post_ids)) {
        wp_send_json_success([
            'groups' => [],
            'total_duplicates' => 0,
            'total_posts' => 0,
            'match_criteria' => []
        ]);
    }

    // Collect post data
    $posts_data = [];
    foreach ($post_ids as $post_id) {
        $post = get_post($post_id);
        $migration_id = get_post_meta($post_id, 'migration_id', true);
        
        // Get date based on post type
        $event_types = ['events', 'tribe_events'];
        if (in_array($post_type, $event_types)) {
            $start_date = get_post_meta($post_id, 'time_date_event_dates_event_start_date', true);
        } else {
            $start_date = $post->post_date;
        }
        
        $posts_data[] = [
            'ID' => $post_id,
            'title' => $post->post_title,
            'slug' => $post->post_name,
            'date' => $start_date ?: $post->post_date,
            'content' => trim($post->post_content),
            'excerpt' => trim($post->post_excerpt),
            'status' => $post->post_status,
            'migration_id' => $migration_id,
            'edit_link' => get_edit_post_link($post_id)
        ];
    }

    // Find duplicates based on selected criteria
    $duplicate_groups = [];
    $processed = [];

    for ($i = 0; $i < count($posts_data); $i++) {
        if (in_array($posts_data[$i]['ID'], $processed)) continue;

        $current = $posts_data[$i];
        $group = [$current];
        $match_details = [];
        $similarity_score = 100;

        for ($j = $i + 1; $j < count($posts_data); $j++) {
            if (in_array($posts_data[$j]['ID'], $processed)) continue;

            $compare = $posts_data[$j];
            
            // Check each selected criterion
            $matches = [];
            
            if ($match_title) {
                $matches['title'] = strtolower($current['title']) === strtolower($compare['title']);
            }
            
            if ($match_date) {
                $matches['date'] = $current['date'] === $compare['date'];
            }
            
            if ($match_content) {
                $matches['content'] = $current['content'] === $compare['content'];
            }
            
            if ($match_excerpt) {
                $matches['excerpt'] = $current['excerpt'] === $compare['excerpt'];
            }
            
            if ($match_migration_id) {
                $matches['migration_id'] = !empty($current['migration_id']) && 
                                          $current['migration_id'] === $compare['migration_id'];
            }

            // All selected criteria must match
            $is_duplicate = !in_array(false, $matches, true);

            if ($is_duplicate) {
                $group[] = $compare;
                $processed[] = $compare['ID'];
                
                // Build match type description
                $matched_fields = [];
                foreach ($matches as $field => $matched) {
                    if ($matched) {
                        $matched_fields[] = ucfirst(str_replace('_', ' ', $field));
                    }
                }
                $match_details = $matched_fields;
                
                // Calculate similarity (100% if migration_id matches, 99% otherwise)
                if (isset($matches['migration_id']) && $matches['migration_id']) {
                    $similarity_score = 100;
                } else {
                    $similarity_score = 99;
                }
            }
        }

        if (count($group) > 1) {
            $duplicate_groups[] = [
                'events' => $group, // Keep 'events' for backward compatibility
                'match_type' => 'Matching: ' . implode(' + ', $match_details),
                'similarity' => $similarity_score
            ];
            $processed[] = $current['ID'];
        }
    }

    $total_duplicates = 0;
    foreach ($duplicate_groups as $group) {
        $total_duplicates += count($group['events']) - 1;
    }

    // Build criteria description
    $criteria_used = [];
    if ($match_title) $criteria_used[] = 'Title';
    if ($match_date) $criteria_used[] = 'Date';
    if ($match_content) $criteria_used[] = 'Content';
    if ($match_excerpt) $criteria_used[] = 'Excerpt';
    if ($match_migration_id) $criteria_used[] = 'Migration ID';

    wp_send_json_success([
        'groups' => $duplicate_groups,
        'total_duplicates' => $total_duplicates,
        'total_posts' => $total_posts,
        'match_criteria' => $criteria_used
    ]);
}

function fwk_ajax_process_duplicate_batch() {
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    @set_time_limit(120);
    
    $groups_json = stripslashes($_POST['groups']);
    $groups = json_decode($groups_json, true);
    $batch_action = sanitize_text_field($_POST['batch_action']);
    $dry_run = !empty($_POST['dry_run']);

    $results = [
        'processed' => 0,
        'merged' => 0,
        'deleted' => 0,
        'errors' => 0,
        'logs' => []
    ];

    if (empty($groups) || !is_array($groups)) {
        wp_send_json_error('Invalid groups data');
    }

    foreach ($groups as $group) {
        $results['processed']++;
        $keeper = $group['events'][0];
        $duplicates = array_slice($group['events'], 1);

        if ($dry_run) {
            // Dry run mode - just log what would happen
            $results['logs'][] = [
                'message' => '📋 Group #' . $results['processed'] . ': Would keep "' . $keeper['title'] . '" (ID: ' . $keeper['ID'] . ')',
                'type' => 'debug'
            ];

            foreach ($duplicates as $dup) {
                if ($batch_action === 'merge') {
                    $results['merged']++;
                    $results['logs'][] = [
                        'message' => '  → Would MERGE #' . $dup['ID'] . ' into #' . $keeper['ID'] . ' then delete',
                        'type' => 'debug'
                    ];
                } else {
                    $results['deleted']++;
                    $results['logs'][] = [
                        'message' => '  → Would DELETE #' . $dup['ID'] . ' (' . $dup['title'] . ')',
                        'type' => 'debug'
                    ];
                }
            }
        } else {
            // Live mode - actually process
            $results['logs'][] = [
                'message' => '✓ Group #' . $results['processed'] . ': Keeping "' . $keeper['title'] . '" (ID: ' . $keeper['ID'] . ')',
                'type' => 'created'
            ];

            foreach ($duplicates as $dup) {
                if ($batch_action === 'merge') {
                    // Merge the duplicate into the keeper
                    $merge_result = fwk_merge_events($dup['ID'], $keeper['ID']);
                    
                    if ($merge_result['success']) {
                        $results['merged']++;
                        $results['logs'][] = [
                            'message' => '  → Merged #' . $dup['ID'] . ' into #' . $keeper['ID'],
                            'type' => 'updated'
                        ];
                    } else {
                        $results['errors']++;
                        $results['logs'][] = [
                            'message' => '  → Error merging #' . $dup['ID'] . ': ' . $merge_result['error'],
                            'type' => 'error'
                        ];
                    }
                } else {
                    // Just delete the duplicate
                    $delete_result = wp_delete_post($dup['ID'], true);
                    
                    if ($delete_result) {
                        $results['deleted']++;
                        $results['logs'][] = [
                            'message' => '  → Deleted #' . $dup['ID'] . ' (' . $dup['title'] . ')',
                            'type' => 'updated'
                        ];
                    } else {
                        $results['errors']++;
                        $results['logs'][] = [
                            'message' => '  → Error deleting #' . $dup['ID'],
                            'type' => 'error'
                        ];
                    }
                }
            }
        }
    }

    wp_send_json_success($results);
}

function fwk_merge_events($from_id, $to_id) {
    $result = ['success' => false, 'error' => ''];

    $from_post = get_post($from_id);
    $to_post = get_post($to_id);

    if (!$from_post || !$to_post) {
        $result['error'] = 'Invalid posts';
        return $result;
    }
    
    if ($from_post->post_type !== $to_post->post_type) {
        $result['error'] = 'Posts must be the same type';
        return $result;
    }

    // Merge content
    if (empty($to_post->post_content) && !empty($from_post->post_content)) {
        wp_update_post(['ID' => $to_id, 'post_content' => $from_post->post_content]);
    }

    // Merge excerpt
    if (empty($to_post->post_excerpt) && !empty($from_post->post_excerpt)) {
        wp_update_post(['ID' => $to_id, 'post_excerpt' => $from_post->post_excerpt]);
    }

    // Merge featured image
    if (!has_post_thumbnail($to_id) && has_post_thumbnail($from_id)) {
        $thumbnail_id = get_post_thumbnail_id($from_id);
        set_post_thumbnail($to_id, $thumbnail_id);
    }

    // Merge ACF fields (only if empty in destination)
    $acf_fields = ['event_website', 'event_cost', 'time_date', 'venue_select', 'organizer_select'];
    foreach ($acf_fields as $field) {
        $to_value = get_field($field, $to_id);
        $from_value = get_field($field, $from_id);
        
        if (empty($to_value) && !empty($from_value)) {
            update_field($field, $from_value, $to_id);
        }
    }

    // Delete the duplicate
    $deleted = wp_delete_post($from_id, true);

    if ($deleted) {
        $result['success'] = true;
    } else {
        $result['error'] = 'Failed to delete duplicate after merge';
    }

    return $result;
}

function fwk_ajax_merge_duplicate() {
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    $from_id = intval($_POST['from_id']);
    $to_id = intval($_POST['to_id']);
    $dry_run = !empty($_POST['dry_run']);

    if (!$from_id || !$to_id) wp_send_json_error('Invalid IDs');

    if ($dry_run) {
        wp_send_json_success('Dry run: Would merge event #' . $from_id . ' into #' . $to_id);
    }

    $result = fwk_merge_events($from_id, $to_id);

    if ($result['success']) {
        wp_send_json_success('Events merged successfully');
    } else {
        wp_send_json_error($result['error']);
    }
}

function fwk_ajax_delete_duplicate() {
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    $event_id = intval($_POST['event_id']);

    if (!$event_id) wp_send_json_error('Invalid ID');

    $post = get_post($event_id);
    if (!$post || $post->post_type !== 'events') {
        wp_send_json_error('Invalid event');
    }

    $result = wp_delete_post($event_id, true);

    if ($result) {
        wp_send_json_success('Event deleted successfully');
    } else {
        wp_send_json_error('Failed to delete event');
    }
}

function fwk_ajax_acf_upload_xml() {
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    if (empty($_FILES['xml_file'])) {
        wp_send_json_error('No file uploaded');
    }

    $file = $_FILES['xml_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        wp_send_json_error('Upload error: ' . $file['error']);
    }

    $xml_content = file_get_contents($file['tmp_name']);
    if (!$xml_content) {
        wp_send_json_error('Could not read file');
    }

    $events = fwk_parse_wxr_events($xml_content);
    if (empty($events)) {
        wp_send_json_error('No tribe_events found in XML');
    }

    $session_id = 'fwk_xml_' . md5(time() . rand());
    set_transient($session_id, $events, HOUR_IN_SECONDS);

    wp_send_json_success([
        'session_id' => $session_id,
        'total' => count($events)
    ]);
}

function fwk_parse_wxr_events($xml_content) {
    $events = [];
    
    libxml_use_internal_errors(true);
    
    $xml = simplexml_load_string($xml_content);
    if (!$xml) {
        return $events;
    }

    $namespaces = $xml->getNamespaces(true);
    
    foreach ($xml->channel->item as $item) {
        $wp = $item->children($namespaces['wp']);
        $content = $item->children($namespaces['content']);
        $excerpt = $item->children($namespaces['excerpt']);
        
        if ((string)$wp->post_type !== 'tribe_events') {
            continue;
        }

        $meta = [];
        foreach ($wp->postmeta as $postmeta) {
            $key = (string)$postmeta->meta_key;
            $value = (string)$postmeta->meta_value;
            $meta[$key] = $value;
        }

        $event = [
            'ID' => (int)$wp->post_id,
            'post_title' => (string)$item->title,
            'post_content' => (string)$content->encoded,
            'post_excerpt' => (string)$excerpt->encoded,
            'post_name' => (string)$wp->post_name,
            'post_status' => (string)$wp->status,
            'post_date' => (string)$wp->post_date,
            'meta_flat' => $meta,
            'featured_media' => null
        ];

        if (!empty($meta['_thumbnail_id'])) {
            $event['_thumbnail_id'] = $meta['_thumbnail_id'];
        }

        $events[] = $event;
    }

    return $events;
}

/**
 * Calculate similarity between two strings using Levenshtein distance
 */
function fwk_calculate_similarity($str1, $str2) {
    $str1 = strtolower(trim($str1));
    $str2 = strtolower(trim($str2));
    
    if ($str1 === $str2) return 100;
    
    $max_len = max(strlen($str1), strlen($str2));
    if ($max_len === 0) return 100;
    
    $levenshtein = levenshtein($str1, $str2);
    $similarity = (1 - ($levenshtein / $max_len)) * 100;
    
    return round($similarity, 2);
}

/**
 * Find existing event/post by migration_id, or title+date+content match
 */
function fwk_find_existing_event($source_id, $source_event, $post_type = 'events', $fuzzy_match = false, $debug_mode = false) {
    $result = [
        'exists' => false,
        'post_id' => 0,
        'match_type' => '',
        'similarity' => 0,
        'debug_info' => []
    ];
    
    // First: match by migration_id (100% match)
    $existing = get_posts([
        'post_type'      => $post_type,
        'meta_key'       => 'migration_id',
        'meta_value'     => $source_id,
        'posts_per_page' => 1,
        'post_status'    => 'any',
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false
    ]);

    if (!empty($existing)) {
        $result['exists'] = true;
        $result['post_id'] = $existing[0];
        $result['match_type'] = 'migration_id';
        $result['similarity'] = 100;
        if ($debug_mode) {
            $result['debug_info'][] = 'Found by migration_id ' . $source_id . ' => Post ID ' . $existing[0];
        }
        return $result;
    }

    // Second: match by title + date + content
    if (!empty($source_event['post_title'])) {
        $source_title = trim($source_event['post_title']);
        $source_content = trim($source_event['post_content'] ?? '');
        
        // Get source date based on post type
        if ($post_type === 'events') {
            $source_date = $source_event['meta_flat']['_EventStartDate'] ?? '';
        } else {
            $source_date = $source_event['post_date'] ?? '';
        }

        // Get all content with matching title
        global $wpdb;
        $matching_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_title = %s AND post_status != 'trash'",
            $post_type,
            $source_title
        ));

        if (!empty($matching_ids)) {
            foreach ($matching_ids as $event_id) {
                $existing_post = get_post($event_id);
                $existing_content = trim($existing_post->post_content);
                
                // Get existing date based on post type
                if ($post_type === 'events') {
                    $existing_date = get_post_meta($event_id, 'time_date_event_dates_event_start_date', true);
                } else {
                    $existing_date = $existing_post->post_date;
                }
                
                // Convert date formats for comparison if needed
                $source_date_compare = $source_date;
                if ($source_date && strtotime($source_date)) {
                    if ($post_type === 'events') {
                        $source_date_obj = new DateTime($source_date);
                        $source_date_compare = $source_date_obj->format('m/d/Y');
                    }
                }

                // Check if date and content match
                $date_match = ($source_date_compare === $existing_date) || (empty($source_date) && empty($existing_date));
                $content_match = ($source_content === $existing_content);

                if ($date_match && $content_match) {
                    $result['exists'] = true;
                    $result['post_id'] = $event_id;
                    $result['match_type'] = 'title_date_content';
                    $result['similarity'] = 99;
                    if ($debug_mode) {
                        $result['debug_info'][] = 'Found by title + date + content match => Post ID ' . $event_id;
                    }
                    return $result;
                }
            }
        }
    }

    if ($debug_mode) {
        $result['debug_info'][] = 'No existing content found for source ID ' . $source_id;
    }

    return $result;
}

function fwk_ajax_acf_import_xml_batch() {
    @set_time_limit(120);
    @ini_set('memory_limit', '512M');

    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    $session_id    = sanitize_text_field($_POST['session_id']);
    $page          = max(1, intval($_POST['page']));
    $per_page      = max(1, min(50, intval($_POST['per_page'])));
    $post_type     = sanitize_text_field($_POST['post_type'] ?? 'post');
    $date_from     = sanitize_text_field($_POST['date_from'] ?? '');
    $date_to       = sanitize_text_field($_POST['date_to'] ?? '');
    $force_images  = !empty($_POST['force_images']);
    $skip_images   = !empty($_POST['skip_images']);
    $only_new      = !empty($_POST['only_new']);
    $only_existing = !empty($_POST['only_existing']);
    $only_images   = !empty($_POST['only_images']);
    $fuzzy_match   = !empty($_POST['fuzzy_match']);
    $debug_mode    = !empty($_POST['debug_mode']);

    $all_events = get_transient($session_id);
    if (!$all_events) {
        wp_send_json_error('Session expired - please re-upload XML');
    }

    // Apply date filtering if specified
    if (!empty($date_from) || !empty($date_to)) {
        $all_events = array_filter($all_events, function($event) use ($date_from, $date_to) {
            $post_date = $event['post_date'] ?? '';
            if (empty($post_date)) return true; // Include if no date

            $event_timestamp = strtotime($post_date);
            if ($event_timestamp === false) return true; // Include if invalid date

            if (!empty($date_from)) {
                $from_timestamp = strtotime($date_from);
                if ($event_timestamp < $from_timestamp) return false;
            }

            if (!empty($date_to)) {
                $to_timestamp = strtotime($date_to . ' 23:59:59');
                if ($event_timestamp > $to_timestamp) return false;
            }

            return true;
        });
        $all_events = array_values($all_events); // Re-index array
    }

    $offset = ($page - 1) * $per_page;
    $events = array_slice($all_events, $offset, $per_page);
    
    $total_events = count($all_events);
    $max_pages = ceil($total_events / $per_page);

    $results = [
        'processed'         => 0,
        'created'           => 0,
        'updated'           => 0,
        'skipped'           => 0,
        'duplicates_found'  => 0,
        'images_downloaded' => 0,
        'venues_linked'     => 0,
        'organizers_linked' => 0,
        'errors'            => [],
        'logs'              => [],
        'has_more'          => ($page < $max_pages) && !empty($events),
        'memory_usage'      => ''
    ];

    if ($debug_mode) {
        $results['logs'][] = [
            'message' => 'Processing batch ' . $page . ': events ' . ($offset + 1) . '-' . min($offset + $per_page, $total_events) . ' of ' . $total_events,
            'type' => 'debug'
        ];
    }

    $start_time = microtime(true);

    foreach ($events as $index => $source_event) {
        if ((microtime(true) - $start_time) > 100) {
            $results['logs'][] = [
                'message' => 'Time limit approaching, stopping batch early',
                'type' => 'error'
            ];
            break;
        }
        
        $source_id = $source_event['ID'];

        // Find existing event using multiple matching methods
        $match_result = fwk_find_existing_event($source_id, $source_event, $fuzzy_match, $debug_mode);
        $exists = $match_result['exists'];
        $post_id = $match_result['post_id'];
        
        if ($debug_mode && !empty($match_result['debug_info'])) {
            foreach ($match_result['debug_info'] as $debug_line) {
                $results['logs'][] = ['message' => '  → ' . $debug_line, 'type' => 'debug'];
            }
        }

        // Track fuzzy duplicates
        if ($match_result['match_type'] === 'fuzzy_title') {
            $results['duplicates_found']++;
        }

        if ($only_new && $exists) {
            $results['processed']++;
            $results['skipped']++;
            if ($debug_mode) $results['logs'][] = ['message' => '⊘ Skipped (exists via ' . $match_result['match_type'] . '): ' . $source_event['post_title'], 'type' => 'skipped'];
            continue;
        }

        if (($only_existing || $only_images) && !$exists) {
            $results['processed']++;
            $results['skipped']++;
            if ($debug_mode) $results['logs'][] = ['message' => '⊘ Skipped (new): ' . $source_event['post_title'], 'type' => 'skipped'];
            continue;
        }

        $result = fwk_import_single_event_acf($source_event, $post_id, $force_images, $debug_mode, $only_images, $skip_images);

        $results['processed']++;

        $log_message = '';
        $log_type    = '';

        if ($result['status'] === 'created') {
            $results['created']++;
            $log_message = '✓ Created: ' . $source_event['post_title'];
            $log_type    = 'created';
        } elseif ($result['status'] === 'updated') {
            $results['updated']++;
            $log_message = '↻ Updated: ' . $source_event['post_title'];
            $log_type    = 'updated';
            if ($match_result['match_type'] === 'fuzzy_title') {
                $log_message .= ' [' . $match_result['similarity'] . '% match]';
            }
        } elseif ($result['status'] === 'image_only') {
            $results['updated']++;
            $log_message = 'Image Updated: ' . $source_event['post_title'];
            $log_type    = 'updated';
        } elseif ($result['status'] === 'skipped') {
            $results['skipped']++;
            $log_message = '⊘ Skipped: ' . $source_event['post_title'];
            $log_type    = 'skipped';
        } elseif ($result['status'] === 'error') {
            $results['errors'][] = $result['message'];
            $log_message = '✗ Error: ' . $source_event['post_title'] . ' - ' . $result['message'];
            $log_type    = 'error';
        }

        if (!empty($result['venue_linked'])) {
            $results['venues_linked']++;
        }
        if (!empty($result['organizer_linked'])) {
            $results['organizers_linked']++;
        }

        if ($result['image_downloaded']) {
            $results['images_downloaded']++;
            $log_message .= ' [Image ✓]';
        } elseif ($result['image_error']) {
            $log_message .= ' [Image ✗: ' . substr($result['image_error'], 0, 50) . ']';
        } elseif ($result['image_skipped']) {
            $log_message .= ' [Image skipped]';
        }

        $results['logs'][] = ['message' => $log_message, 'type' => $log_type];

        if ($debug_mode && !empty($result['debug_info'])) {
            foreach ($result['debug_info'] as $debug_line) {
                $results['logs'][] = ['message' => '  → ' . $debug_line, 'type' => 'debug'];
            }
        }
        
        unset($result);
        
        if ($index % 5 === 0 && function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }

    $results['memory_usage'] = size_format(memory_get_peak_usage(true));

    wp_send_json_success($results);
}

function fwk_ajax_acf_get_total_count() {
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    $source_url = sanitize_text_field($_POST['source_url']);
    $url        = add_query_arg(['paged' => 1, 'per_page' => 1], $source_url);
    $response   = wp_remote_get($url, ['timeout' => 30]);

    if (is_wp_error($response)) wp_send_json_error($response->get_error_message());

    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (!$data || !isset($data['total'])) wp_send_json_error('Invalid API response');

    wp_send_json_success(['total' => intval($data['total'])]);
}

function fwk_ajax_acf_import_batch() {
    @set_time_limit(120);
    @ini_set('memory_limit', '512M');

    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    $source_url    = sanitize_text_field($_POST['source_url']);
    $page          = max(1, intval($_POST['page']));
    $per_page      = max(1, min(50, intval($_POST['per_page'])));
    $post_type     = sanitize_text_field($_POST['post_type'] ?? 'post');
    $date_from     = sanitize_text_field($_POST['date_from'] ?? '');
    $date_to       = sanitize_text_field($_POST['date_to'] ?? '');
    $force_images  = !empty($_POST['force_images']);
    $skip_images   = !empty($_POST['skip_images']);
    $only_new      = !empty($_POST['only_new']);
    $only_existing = !empty($_POST['only_existing']);
    $only_images   = !empty($_POST['only_images']);
    $fuzzy_match   = !empty($_POST['fuzzy_match']);
    $debug_mode    = !empty($_POST['debug_mode']);

    $url = add_query_arg([
        'paged'    => $page,
        'per_page' => $per_page,
    ], $source_url);

    $start_time = microtime(true);
    
    $response = wp_remote_get($url, [
        'timeout' => 60,
        'httpversion' => '1.1',
        'headers' => ['Accept-Encoding' => 'gzip, deflate']
    ]);
    
    $fetch_time = round(microtime(true) - $start_time, 2);
    
    if (is_wp_error($response)) {
        wp_send_json_error('Source API error after ' . $fetch_time . 's: ' . $response->get_error_message());
    }
    
    $http_code = wp_remote_retrieve_response_code($response);
    if ($http_code !== 200) {
        wp_send_json_error('Source API returned HTTP ' . $http_code . ' after ' . $fetch_time . 's');
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (!$data || !isset($data['posts'])) {
        wp_send_json_error('Invalid API response after ' . $fetch_time . 's');
    }

    $events  = $data['posts'];

    // Apply date filtering if specified
    if (!empty($date_from) || !empty($date_to)) {
        $events = array_filter($events, function($event) use ($date_from, $date_to) {
            $post_date = $event['post_date'] ?? '';
            if (empty($post_date)) return true; // Include if no date

            $event_timestamp = strtotime($post_date);
            if ($event_timestamp === false) return true; // Include if invalid date

            if (!empty($date_from)) {
                $from_timestamp = strtotime($date_from);
                if ($event_timestamp < $from_timestamp) return false;
            }

            if (!empty($date_to)) {
                $to_timestamp = strtotime($date_to . ' 23:59:59');
                if ($event_timestamp > $to_timestamp) return false;
            }

            return true;
        });
        $events = array_values($events); // Re-index array
    }

    $results = [
        'processed'         => 0,
        'created'           => 0,
        'updated'           => 0,
        'skipped'           => 0,
        'duplicates_found'  => 0,
        'images_downloaded' => 0,
        'venues_linked'     => 0,
        'organizers_linked' => 0,
        'errors'            => [],
        'logs'              => [],
        'has_more'          => ($page < ($data['max_pages'] ?? 1)) && !empty($events),
        'memory_usage'      => ''
    ];

    if ($debug_mode) {
        $results['logs'][] = [
            'message' => 'Fetched ' . count($events) . ' events from source in ' . $fetch_time . 's',
            'type' => 'debug'
        ];
    }

    foreach ($events as $index => $source_event) {
        if ((microtime(true) - $start_time) > 100) {
            $results['logs'][] = [
                'message' => 'Time limit approaching, stopping batch early at event ' . ($index + 1),
                'type' => 'error'
            ];
            break;
        }
        
        $source_id = $source_event['ID'];

        // Find existing event using multiple matching methods
        $match_result = fwk_find_existing_event($source_id, $source_event, $fuzzy_match, $debug_mode);
        $exists = $match_result['exists'];
        $post_id = $match_result['post_id'];
        
        if ($debug_mode && !empty($match_result['debug_info'])) {
            foreach ($match_result['debug_info'] as $debug_line) {
                $results['logs'][] = ['message' => '  → ' . $debug_line, 'type' => 'debug'];
            }
        }

        // Track fuzzy duplicates
        if ($match_result['match_type'] === 'fuzzy_title') {
            $results['duplicates_found']++;
        }

        if ($only_new && $exists) {
            $results['processed']++;
            $results['skipped']++;
            if ($debug_mode) $results['logs'][] = ['message' => '⊘ Skipped (exists via ' . $match_result['match_type'] . '): ' . $source_event['post_title'], 'type' => 'skipped'];
            continue;
        }

        if (($only_existing || $only_images) && !$exists) {
            $results['processed']++;
            $results['skipped']++;
            if ($debug_mode) $results['logs'][] = ['message' => '⊘ Skipped (new): ' . $source_event['post_title'], 'type' => 'skipped'];
            continue;
        }

        $result = fwk_import_single_event_acf($source_event, $post_id, $force_images, $debug_mode, $only_images, $skip_images);

        $results['processed']++;

        $log_message = '';
        $log_type    = '';

        if ($result['status'] === 'created') {
            $results['created']++;
            $log_message = '✓ Created: ' . $source_event['post_title'];
            $log_type    = 'created';
        } elseif ($result['status'] === 'updated') {
            $results['updated']++;
            $log_message = '↻ Updated: ' . $source_event['post_title'];
            $log_type    = 'updated';
            if ($match_result['match_type'] === 'fuzzy_title') {
                $log_message .= ' [' . $match_result['similarity'] . '% match]';
            }
        } elseif ($result['status'] === 'image_only') {
            $results['updated']++;
            $log_message = 'Image Updated: ' . $source_event['post_title'];
            $log_type    = 'updated';
        } elseif ($result['status'] === 'skipped') {
            $results['skipped']++;
            $log_message = '⊘ Skipped: ' . $source_event['post_title'];
            $log_type    = 'skipped';
        } elseif ($result['status'] === 'error') {
            $results['errors'][] = $result['message'];
            $log_message = '✗ Error: ' . $source_event['post_title'] . ' - ' . $result['message'];
            $log_type    = 'error';
        }

        if (!empty($result['venue_linked'])) {
            $results['venues_linked']++;
        }
        if (!empty($result['organizer_linked'])) {
            $results['organizers_linked']++;
        }

        if ($result['image_downloaded']) {
            $results['images_downloaded']++;
            $log_message .= ' [Image ✓]';
        } elseif ($result['image_error']) {
            $log_message .= ' [Image ✗: ' . substr($result['image_error'], 0, 50) . ']';
        } elseif ($result['image_skipped']) {
            $log_message .= ' [Image skipped]';
        }

        $results['logs'][] = ['message' => $log_message, 'type' => $log_type];

        if ($debug_mode && !empty($result['debug_info'])) {
            foreach ($result['debug_info'] as $debug_line) {
                $results['logs'][] = ['message' => '  → ' . $debug_line, 'type' => 'debug'];
            }
        }
        
        unset($result);
        
        if ($index % 5 === 0 && function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }

    $results['memory_usage'] = size_format(memory_get_peak_usage(true));

    wp_send_json_success($results);
}

function fwk_find_venue_by_migration_id($source_venue_id, $debug_mode = false) {
    $debug_info = [];
    
    $existing_venue = get_posts([
        'post_type'      => 'event-venue',
        'meta_key'       => 'migration_id',
        'meta_value'     => $source_venue_id,
        'posts_per_page' => 1,
        'post_status'    => 'any',
        'fields'         => 'ids',
        'no_found_rows'  => true
    ]);

    if (!empty($existing_venue)) {
        if ($debug_mode) {
            $debug_info[] = 'Found venue by migration_id ' . $source_venue_id . ' => Post ID ' . $existing_venue[0];
        }
        return [
            'venue_id' => $existing_venue[0],
            'found' => true,
            'debug_info' => $debug_info
        ];
    }

    if ($debug_mode) {
        $debug_info[] = 'No venue found with migration_id: ' . $source_venue_id;
    }

    return [
        'venue_id' => 0,
        'found' => false,
        'debug_info' => $debug_info
    ];
}

function fwk_find_organizer_by_migration_id($source_organizer_id, $debug_mode = false) {
    $debug_info = [];
    
    $existing_organizer = get_posts([
        'post_type'      => 'event-organizer',
        'meta_key'       => 'migration_id',
        'meta_value'     => $source_organizer_id,
        'posts_per_page' => 1,
        'post_status'    => 'any',
        'fields'         => 'ids',
        'no_found_rows'  => true
    ]);

    if (!empty($existing_organizer)) {
        if ($debug_mode) {
            $debug_info[] = 'Found organizer by migration_id ' . $source_organizer_id . ' => Post ID ' . $existing_organizer[0];
        }
        return [
            'organizer_id' => $existing_organizer[0],
            'found' => true,
            'debug_info' => $debug_info
        ];
    }

    if ($debug_mode) {
        $debug_info[] = 'No organizer found with migration_id: ' . $source_organizer_id;
    }

    return [
        'organizer_id' => 0,
        'found' => false,
        'debug_info' => $debug_info
    ];
}

function fwk_import_single_event_acf($source_event, $existing_post_id, $force_images = false, $debug_mode = false, $only_images = false, $skip_images = false) {
    $result = [
        'status'             => 'skipped',
        'message'            => '',
        'post_id'            => 0,
        'image_downloaded'   => false,
        'image_error'        => '',
        'image_skipped'      => false,
        'venue_linked'       => false,
        'organizer_linked'   => false,
        'debug_info'         => []
    ];

    try {
        $source_id = $source_event['ID'];
        $is_update = (bool) $existing_post_id;

        if ($debug_mode) {
            $result['debug_info'][] = 'Source ID: ' . $source_id;
            $result['debug_info'][] = 'Mode: ' . ($only_images ? 'Images Only' : ($is_update ? 'Update' : 'Create'));
            if ($existing_post_id) {
                $result['debug_info'][] = 'Existing Post ID: ' . $existing_post_id;
            }
        }

        if ($only_images) {
            if (!$existing_post_id) {
                $result['status']  = 'skipped';
                $result['message'] = 'Event does not exist';
                return $result;
            }

            $result['post_id'] = $existing_post_id;
            $result['status']  = 'image_only';

            if ($skip_images) {
                $result['image_skipped'] = true;
                return $result;
            }

            $featured_media = $source_event['featured_media'] ?? null;
            if ($featured_media && !empty($featured_media['src'])) {
                $has_thumb = get_post_thumbnail_id($existing_post_id);
                $should_download = $force_images || !$has_thumb;

                if ($should_download) {
                    $image_result = fwk_download_and_attach_image(
                        $featured_media['src'],
                        $existing_post_id,
                        $source_event['post_title'],
                        $debug_mode
                    );

                    if (!empty($image_result['success'])) {
                        $result['image_downloaded'] = true;
                    } else {
                        $result['image_error'] = $image_result['error'] ?? 'unknown error';
                    }
                }
            }

            return $result;
        }

        $post_data = [
            'post_title'   => $source_event['post_title'] ?? '',
            'post_content' => $source_event['post_content'] ?? '',
            'post_excerpt' => $source_event['post_excerpt'] ?? '',
            'post_status'  => $source_event['post_status'] ?? 'publish',
            'post_type'    => $source_event['post_type'] ?? 'post',
            'post_name'    => $source_event['post_name'] ?? '',
        ];

        if ($is_update) {
            $post_data['ID'] = $existing_post_id;
            $existing_post = get_post($existing_post_id);
            
            $source_content = trim($source_event['post_content'] ?? '');
            $existing_content = trim($existing_post->post_content);
            if (empty($source_content) || $source_content === $existing_content) {
                unset($post_data['post_content']);
            }
            
            $source_excerpt = trim($source_event['post_excerpt'] ?? '');
            $existing_excerpt = trim($existing_post->post_excerpt);
            if (empty($source_excerpt) || $source_excerpt === $existing_excerpt) {
                unset($post_data['post_excerpt']);
            }

            $source_title = trim($source_event['post_title'] ?? '');
            $existing_title = trim($existing_post->post_title);
            if (empty($source_title) || $source_title === $existing_title) {
                unset($post_data['post_title']);
            }

            $post_id = wp_update_post($post_data, true);
            $result['status'] = 'updated';
        } else {
            $post_id = wp_insert_post($post_data, true);
            $result['status'] = 'created';
        }

        if (is_wp_error($post_id)) {
            $result['status']  = 'error';
            $result['message'] = $post_id->get_error_message();
            return $result;
        }

        $result['post_id'] = $post_id;

        update_post_meta($post_id, 'migration_id', $source_id);

        $meta = $source_event['meta_flat'] ?? [];
        
        // Event-specific ACF field handling (only for 'events' or 'tribe_events' post types)
        $post_type = $source_event['post_type'] ?? 'post';
        $is_event_type = in_array($post_type, ['events', 'tribe_events']);
        
        if ($is_event_type) {
            // Handle event-specific ACF fields
            if (!empty($meta['_EventURL'])) {
                $existing = get_field('event_website', $post_id);
                $new_value = $meta['_EventURL'];
                if (empty($existing) || $existing !== $new_value) {
                    update_field('event_website', $new_value, $post_id);
                }
            }

            $new_cost_range = $meta['_EventCost'] ?? '';
            $new_cost_details = $meta['_EventCostDescription'] ?? '';
            if (!empty($new_cost_range) || !empty($new_cost_details)) {
                $existing = get_field('event_cost', $post_id);
                $existing_range = $existing['cost_range'] ?? '';
                $existing_details = $existing['event_cost_details'] ?? '';
                
                if ($existing_range !== $new_cost_range || $existing_details !== $new_cost_details) {
                    update_field('event_cost', [
                        'cost_range' => $new_cost_range,
                        'event_cost_details' => $new_cost_details
                    ], $post_id);
                }
            }

            $all_day = ($meta['_EventAllDay'] ?? '') === 'yes';
            $start_date = $meta['_EventStartDate'] ?? '';
            $end_date = $meta['_EventEndDate'] ?? '';

            if ($start_date) {
                try {
                    $start_dt = new DateTime($start_date);
                    $end_dt = $end_date ? new DateTime($end_date) : $start_dt;

                    $start_time = $start_dt->format('H:i');
                    $end_time = $end_dt->format('H:i');
                    if ($start_time === '00:00' && $end_time === '23:59') {
                        $all_day = true;
                    }

                    $new_time_date = [
                        'all_day_event' => $all_day ? 1 : 0,
                        'event_dates' => [
                            'event_start_date' => $start_dt->format('m/d/Y'),
                            'event_end_date' => $end_dt->format('m/d/Y')
                        ]
                    ];

                    if (!$all_day) {
                        $new_time_date['event_duration'] = [
                            'event_start_time' => $start_dt->format('g:i a'),
                            'event_end_time' => $end_dt->format('g:i a')
                        ];
                    }

                    $existing = get_field('time_date', $post_id);
                    $existing_start = $existing['event_dates']['event_start_date'] ?? '';
                    $existing_end = $existing['event_dates']['event_end_date'] ?? '';
                    $existing_all_day = $existing['all_day_event'] ?? 0;
                    
                    $is_different = ($existing_start !== $new_time_date['event_dates']['event_start_date']) ||
                                    ($existing_end !== $new_time_date['event_dates']['event_end_date']) ||
                                    ($existing_all_day != $new_time_date['all_day_event']);
                    
                    if (!$all_day && !$is_different) {
                        $existing_start_time = $existing['event_duration']['event_start_time'] ?? '';
                        $existing_end_time = $existing['event_duration']['event_end_time'] ?? '';
                        $is_different = ($existing_start_time !== $new_time_date['event_duration']['event_start_time']) ||
                                        ($existing_end_time !== $new_time_date['event_duration']['event_end_time']);
                    }

                    if (empty($existing) || $is_different) {
                        update_field('time_date', $new_time_date, $post_id);
                    }
                } catch (Exception $e) {
                    if ($debug_mode) {
                        $result['debug_info'][] = 'DateTime error: ' . $e->getMessage();
                    }
                }
            }

            // Venue linking
            $venue_id_source = $meta['_EventVenueID'] ?? '';
            if ($venue_id_source) {
                $venue_result = fwk_find_venue_by_migration_id($venue_id_source, $debug_mode);
                
                if ($venue_result['found']) {
                    $current_venue_id = (int)get_post_meta($post_id, 'venue_select_venue_select', true);
                    
                    if ($current_venue_id !== $venue_result['venue_id']) {
                        update_post_meta($post_id, 'venue_select_choose_venue', 0);
                        update_post_meta($post_id, 'venue_select_venue_select', $venue_result['venue_id']);
                        update_post_meta($post_id, '_venue_select_venue_select', 'field_690e25fbefa64');
                        
                        $result['venue_linked'] = true;
                        
                        if ($debug_mode) {
                            $result['debug_info'][] = 'Linked venue: migration_id ' . $venue_id_source . ' => post ID ' . $venue_result['venue_id'];
                        }
                    } else {
                        if ($debug_mode) {
                            $result['debug_info'][] = 'Venue already linked to post ID ' . $current_venue_id;
                        }
                    }
                    
                    if ($debug_mode) {
                        $result['debug_info'] = array_merge($result['debug_info'], $venue_result['debug_info']);
                    }
                } else {
                    if ($debug_mode) {
                        $result['debug_info'][] = 'Venue not found for migration_id: ' . $venue_id_source;
                    }
                }
            }

            // Organizer linking
            $organizer_id_source = $meta['_EventOrganizerID'] ?? '';
            if ($organizer_id_source) {
                $organizer_result = fwk_find_organizer_by_migration_id($organizer_id_source, $debug_mode);
                
                if ($organizer_result['found']) {
                    $current_organizer_id = (int)get_post_meta($post_id, 'organizer_select_organizer_select', true);
                    
                    if ($current_organizer_id !== $organizer_result['organizer_id']) {
                        update_post_meta($post_id, 'organizer_select_choose_organizer', 0);
                        update_post_meta($post_id, 'organizer_select_organizer_select', $organizer_result['organizer_id']);
                        update_post_meta($post_id, '_organizer_select_organizer_select', 'field_69124e7d94c04');
                        
                        $result['organizer_linked'] = true;
                        
                        if ($debug_mode) {
                            $result['debug_info'][] = 'Linked organizer: migration_id ' . $organizer_id_source . ' => post ID ' . $organizer_result['organizer_id'];
                        }
                    } else {
                        if ($debug_mode) {
                            $result['debug_info'][] = 'Organizer already linked to post ID ' . $current_organizer_id;
                        }
                    }
                    
                    if ($debug_mode) {
                        $result['debug_info'] = array_merge($result['debug_info'], $organizer_result['debug_info']);
                    }
                } else {
                    if ($debug_mode) {
                        $result['debug_info'][] = 'Organizer not found for migration_id: ' . $organizer_id_source;
                    }
                }
            }
        } // End event-specific ACF handling
        
        // Handle categories and tags (for all post types)
        if (!empty($source_event['terms_simple'])) {
            $terms = $source_event['terms_simple'];
            
            // Handle categories
            if (!empty($terms['category'])) {
                $categories = array_map('sanitize_text_field', $terms['category']);
                wp_set_post_terms($post_id, $categories, 'category', false);
            }
            
            // Handle tags
            if (!empty($terms['post_tag'])) {
                $tags = array_map('sanitize_text_field', $terms['post_tag']);
                wp_set_post_terms($post_id, $tags, 'post_tag', false);
            }
        }


        if ($skip_images) {
            $result['image_skipped'] = true;
        } else {
            $featured_media = $source_event['featured_media'] ?? null;
            if ($featured_media && !empty($featured_media['src'])) {
                $has_thumb = get_post_thumbnail_id($post_id);
                $should_download = $force_images || !$has_thumb;

                if ($should_download) {
                    $image_result = fwk_download_and_attach_image(
                        $featured_media['src'],
                        $post_id,
                        $source_event['post_title'],
                        $debug_mode
                    );

                    if (!empty($image_result['success'])) {
                        $result['image_downloaded'] = true;
                    } else {
                        $result['image_error'] = $image_result['error'] ?? 'unknown error';
                    }
                }
            }
        }

        return $result;
        
    } catch (Exception $e) {
        $result['status'] = 'error';
        $result['message'] = 'PHP Exception: ' . $e->getMessage();
        return $result;
    } catch (Error $e) {
        $result['status'] = 'error';
        $result['message'] = 'PHP Error: ' . $e->getMessage();
        return $result;
    }
}

function fwk_download_and_attach_image($image_url, $post_id, $title, $debug_mode = false) {
    $result = ['success' => false, 'error' => '', 'attachment_id' => 0];

    if (empty($image_url)) {
        $result['error'] = 'No image URL';
        return $result;
    }

    $image_url = stripslashes($image_url);

    if (strpos($image_url, '//') === 0) {
        $image_url = 'https:' . $image_url;
    }

    if (!preg_match('/^https?:\/\//i', $image_url)) {
        $result['error'] = 'Invalid URL format';
        return $result;
    }

    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');

    $tmp = download_url($image_url, 15);

    if (is_wp_error($tmp)) {
        $result['error'] = $tmp->get_error_message();
        return $result;
    }

    $path_parts = pathinfo(parse_url($image_url, PHP_URL_PATH));
    $extension  = isset($path_parts['extension']) ? strtolower($path_parts['extension']) : 'jpg';

    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($extension, $allowed)) {
        $extension = 'jpg';
    }

    $file_array = [
        'name'     => sanitize_file_name(substr($title, 0, 50)) . '-' . time() . '.' . $extension,
        'tmp_name' => $tmp
    ];

    $attachment_id = media_handle_sideload($file_array, $post_id, $title);

    if (file_exists($tmp)) {
        @unlink($tmp);
    }

    if (is_wp_error($attachment_id)) {
        $result['error'] = $attachment_id->get_error_message();
        return $result;
    }

    set_post_thumbnail($post_id, $attachment_id);

    $result['success'] = true;
    $result['attachment_id'] = $attachment_id;

    return $result;
}

// ============================================================================
// BULK UPDATE HANDLER
// ============================================================================

function fwk_ajax_bulk_update_posts() {
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    @set_time_limit(120);
    @ini_set('memory_limit', '512M');

    $post_type = sanitize_text_field($_POST['post_type']);
    $status = sanitize_text_field($_POST['status']);
    $limit = max(1, min(1000, intval($_POST['limit'])));
    $actions = json_decode(stripslashes($_POST['actions']), true);
    $dry_run = !empty($_POST['dry_run']);

    $args = [
        'post_type' => $post_type,
        'post_status' => $status,
        'posts_per_page' => $limit,
        'fields' => 'ids'
    ];

    $post_ids = get_posts($args);

    $results = [
        'processed' => 0,
        'updated' => 0,
        'errors' => 0,
        'logs' => []
    ];

    if (empty($post_ids)) {
        $results['logs'][] = ['message' => 'No posts found matching criteria', 'type' => 'error'];
        wp_send_json_success($results);
    }

    $results['logs'][] = [
        'message' => 'Found ' . count($post_ids) . ' posts to process',
        'type' => 'created'
    ];

    if ($dry_run) {
        $results['logs'][] = [
            'message' => 'DRY RUN MODE - No changes will be made',
            'type' => 'debug'
        ];
    }

    foreach ($post_ids as $post_id) {
        $results['processed']++;
        $post = get_post($post_id);
        $updated = false;

        $update_data = ['ID' => $post_id];

        // Change status
        if (!empty($actions['change_status'])) {
            $new_status = sanitize_text_field($actions['change_status']);
            if ($post->post_status !== $new_status) {
                $update_data['post_status'] = $new_status;
                $updated = true;
                $results['logs'][] = [
                    'message' => '#' . $post_id . ': Status ' . $post->post_status . ' → ' . $new_status,
                    'type' => $dry_run ? 'debug' : 'updated'
                ];
            }
        }

        // Change author
        if (!empty($actions['change_author'])) {
            $new_author = intval($actions['change_author']);
            if ($post->post_author != $new_author) {
                $update_data['post_author'] = $new_author;
                $updated = true;
                $results['logs'][] = [
                    'message' => '#' . $post_id . ': Author changed to user #' . $new_author,
                    'type' => $dry_run ? 'debug' : 'updated'
                ];
            }
        }

        // Regenerate excerpt
        if (!empty($actions['regenerate_excerpts'])) {
            $content = strip_tags($post->post_content);
            $words = explode(' ', $content);
            $excerpt = implode(' ', array_slice($words, 0, 150));
            if ($post->post_excerpt !== $excerpt) {
                $update_data['post_excerpt'] = $excerpt;
                $updated = true;
                $results['logs'][] = [
                    'message' => '#' . $post_id . ': Excerpt regenerated',
                    'type' => $dry_run ? 'debug' : 'updated'
                ];
            }
        }

        // Update dates
        if (!empty($actions['update_dates'])) {
            $now = current_time('mysql');
            $update_data['post_date'] = $now;
            $update_data['post_date_gmt'] = get_gmt_from_date($now);
            $update_data['post_modified'] = $now;
            $update_data['post_modified_gmt'] = get_gmt_from_date($now);
            $updated = true;
            $results['logs'][] = [
                'message' => '#' . $post_id . ': Dates updated to now',
                'type' => $dry_run ? 'debug' : 'updated'
            ];
        }

        if ($updated && !$dry_run) {
            $result = wp_update_post($update_data, true);
            if (is_wp_error($result)) {
                $results['errors']++;
                $results['logs'][] = [
                    'message' => '#' . $post_id . ': ERROR - ' . $result->get_error_message(),
                    'type' => 'error'
                ];
            } else {
                $results['updated']++;
            }
        } elseif ($updated && $dry_run) {
            $results['updated']++;
        }
    }

    $results['logs'][] = [
        'message' => 'Processed ' . $results['processed'] . ' posts',
        'type' => 'created'
    ];
    $results['logs'][] = [
        'message' => ($dry_run ? 'Would update: ' : 'Updated: ') . $results['updated'],
        'type' => 'created'
    ];

    wp_send_json_success($results);
}
