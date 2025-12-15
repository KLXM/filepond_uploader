/**
 * MediaPool FilePond Integration
 * Adds a compact FilePond upload area to MediaPool list
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('MediaPool FilePond script loaded');
    
    // Check if we're on MediaPool list page
    const hasTable = document.querySelector('table.table-striped');
    console.log('Has MediaPool table:', !!hasTable);
    
    if (!hasTable) {
        console.log('Not on MediaPool list page, exiting');
        return;
    }
    
    const config = window.mediapool_filepond_config;
    console.log('MediaPool config:', config);
    
    if (!config) {
        console.log('No MediaPool config found, exiting');
        return;
    }
    
    console.log('Creating FilePond upload area...');
    
    // Create FilePond upload area
    const uploadArea = document.createElement('div');
    uploadArea.className = 'panel panel-default mb-3';
    uploadArea.innerHTML = `
        <div class="panel-body" style="padding: 15px;">
            <div class="row">
                <div class="col-sm-12">
                    <h5><i class="rex-icon rex-icon-upload"></i> Dateien hochladen</h5>
                    <form action="index.php" method="post" enctype="multipart/form-data" class="filepond-form">
                        <input type="hidden" name="page" value="mediapool">
                        <input type="hidden" name="subpage" value="upload">
                        <input type="hidden" name="rex_file_category" value="${config.current_category}">
                        <input type="hidden" name="_csrf_token" value="${config.csrf_token}">
                        <input type="file" name="file_new[]" multiple class="filepond-input">
                    </form>
                </div>
            </div>
        </div>
    `;
    
    // Insert before the first table
    const table = document.querySelector('table.table-striped');
    table.parentNode.insertBefore(uploadArea, table);
    console.log('Upload area inserted');
    
    // Initialize FilePond if available
    if (window.FilePond && window.initializeFilePond) {
        console.log('Initializing FilePond...');
        const input = uploadArea.querySelector('.filepond-input');
        window.initializeFilePond(input);
        console.log('FilePond initialized');
    } else {
        console.log('FilePond or initializeFilePond not available');
        console.log('FilePond:', !!window.FilePond);
        console.log('initializeFilePond:', !!window.initializeFilePond);
    }
});