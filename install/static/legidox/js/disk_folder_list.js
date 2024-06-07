/*
*
* Extended functionality of disk.folder.list template page
* for LEGIDOX module
* author: Kuznetsov D. M. @ IDC TULA
*
* */

const divTree = document.querySelector('#ld-tree');
const divNodata = document.querySelector('#ld-nodata');
const divLoading = document.querySelector('#ld-loading');
const $treeContainer = $('#legidox-tree-container');

// Function to update the UI state
function setState(state) {
    switch (state) {
        case "loading":
            divTree.classList.add("visually-hidden");
            divNodata.classList.add("visually-hidden");
            divLoading.classList.remove("visually-hidden");
            break;
        case "nodata":
            divTree.classList.add("visually-hidden");
            divNodata.classList.remove("visually-hidden");
            divLoading.classList.add("visually-hidden");
            break;
        case "display":
            divTree.classList.remove("visually-hidden");
            divNodata.classList.add("visually-hidden");
            divLoading.classList.add("visually-hidden");
            break;
        default:
            divTree.classList.add("visually-hidden");
            divNodata.classList.add("visually-hidden");
            divLoading.classList.remove("visually-hidden");
    }
}

// Function to sanitize search terms
function sanitizeString(inputString) {
    let safeString = inputString.toLowerCase();
    safeString = safeString.replace(/[^\p{L}\p{N}\s-]/gu, ' ');
    return safeString;
}

// Function to build the document tree
function buildTree($treeContainer, legalDocuments) {
    $treeContainer.empty();
    if (legalDocuments && Object.keys(legalDocuments).length > 0 ){
        makeTreeNode(legalDocuments, $treeContainer);

        $('.legidox-doclink').on('click', function (e) {
            e.preventDefault();

            var filePath = e.target.dataset.openFile;
            var fileUrl = `/legidox/file/${filePath}`;

            if (BX && filePath) {
                BX.SidePanel.Instance.open(fileUrl, {
                    requestMethod: "post",
                    requestParams: {
                        IFRAME: "N",
                        SIDEPANEL_OVERRIDE: "Y"
                    },
                    width: 1024,
                    cacheable: false
                });
            } else {
                window.open(fileUrl, '_blank').focus();
            }
        });

        setState('display');
    } else {
        setState('nodata');
    }
}

// Function to create tree nodes
function makeTreeNode(sortedDocuments, $parent) {
    Object.keys(sortedDocuments)
        .sort() // Sort the keys alphabetically
        .forEach(nodeName => {
            let nodeContents = sortedDocuments[nodeName];
            const $nodeLi = $("<li>");
            if (typeof(nodeContents) === "object" && !nodeContents.hasOwnProperty("DOCUMENT_NAME")) {
                const $details = $("<details>").attr('data-search-term', sanitizeString(nodeName));
                const $summary = $("<summary>").text(nodeName);
                $details.append($summary);
                const $childrenList = $("<ul>");
                $details.append($childrenList);
                $nodeLi.append($details);
                makeTreeNode(nodeContents, $childrenList); // Recurse to build child nodes
            } else {
                //$nodeLi.html(`<a href="/legidox/file/${nodeContents['FILENAME']}" data-search-term="${sanitizeString(nodeContents['DOCUMENT_NAME'])} ${nodeContents['DOC_WATCH_LNAMES']}">${nodeContents['DOCUMENT_NAME']}</a>`);
                $nodeLi.html(`<a class="legidox-doclink" href="#" data-open-file="${nodeContents['FILENAME']}" data-search-term="${sanitizeString(nodeContents['DOCUMENT_NAME'])} ${nodeContents['DOC_WATCH_LNAMES']}">${nodeContents['DOCUMENT_NAME']}</a>`);
            }

            $parent.append($nodeLi);
        });
}

// Function to update the document tree
function updateTree($treeContainer, priorityTag = "LD_TAG_DOCTYPE", customSorting = "false") {
    if (priorityTag === "misc") {
        return;
    }
    setState();
    var pageMode = 'PUBLISHED';
    if (window.location.href.includes('pending')) {
        pageMode = 'PENDING';
    }

    if (typeof jQuery !== 'undefined') {
        $.ajax({
            url: `/legidox/tools/ajax.php?mode=get_tree&priority_tag=${priorityTag}&custom_sorting=${customSorting}&page_mode=${pageMode}`,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response && response.data) {
                    buildTree($treeContainer, response.data);
                } else {
                    setState('nodata');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error fetching legal documents data:', error);
            }
        });
    } else {
        console.debug("JQuery not loaded at this point, can't continue");
    }
}

// Function to filter nodes based on search text
function filterNodes(nodeContainer, searchTerms) {
    var oDocumentTree = nodeContainer.querySelectorAll('li, details, a');
    oDocumentTree.forEach(node => {
        node.classList.add('visually-hidden');
        if (node.tagName === 'DETAILS') {
            node.removeAttribute('open');
        }
    });

    var foundSomething = false;

    oDocumentTree.forEach(node => {
        const textContent = node.getAttribute('data-search-term') || "";
        const nodeTerms = textContent.split(/[, ]+/).map(term => term.trim().toLowerCase());
        if (searchTerms.every(term => nodeTerms.some(nodeTerm => nodeTerm.includes(term)))) {
            showParentAndChildrenNodes(node, nodeContainer.id);
            foundSomething = true;
        }
    });

    if (foundSomething === false) {
        setState("nodata");
    } else {
        setState("display");
    }
}

// Function to show all nodes
function showAllNodes(nodeContainer) {
    nodeContainer.querySelectorAll('li, details, a').forEach(node => {
        node.classList.remove('visually-hidden');
        if (node.tagName === 'DETAILS') {
            node.removeAttribute('open');
        }
    });
}

// Function to show parent and children nodes for selected node
function showParentAndChildrenNodes(node, treeContainerId) {
    node.classList.remove('visually-hidden');
    if (node.tagName === 'DETAILS') {
        node.setAttribute('open', 'true')
    }

    let parentNode = node.parentNode;
    while (parentNode && parentNode.getAttribute('id') !== treeContainerId) {
        parentNode.classList.remove('visually-hidden');
        if (parentNode.tagName === 'DETAILS') {
            parentNode.setAttribute('open', 'true');
        }
        parentNode = parentNode.parentNode;
    }

    node.querySelectorAll('li, details, a').forEach(childNode => {
        childNode.classList.remove('visually-hidden');
        if (childNode.tagName === 'DETAILS') {
            childNode.setAttribute('open', 'true');
        }
    });
}

// Set initial state and update tree on page load
setState();
updateTree($treeContainer);

// Attach event listeners
document.querySelectorAll('.legi-btn-sorter').forEach(btnSorter => {
    btnSorter.addEventListener('click', function(e) {
        let priorityTag = btnSorter.dataset.priorityTag;
        updateTree($treeContainer, priorityTag);
    });
});

$('#ld-search').on('input', function() {
    const searchText = $(this).val().trim().toLowerCase();
    const nodeContainer = document.querySelector('#legidox-tree-container');
    if (searchText === '') {
        showAllNodes(nodeContainer);
        setState("display");
    } else {
        const searchTerms = searchText.split(/[, ]+/);
        filterNodes(nodeContainer, searchTerms);
    }
});

// Attach event listeners for tag sorting modal
$('#legidoxTagSortModal').on('shown.bs.modal', function () {
    const sortable = new Sortable(document.getElementById('legidoxTagSortableList'), {
        animation: 150,
    });

    $('#legidoxTagSortableList .form-check-input').on('change', function () {
        const $checkbox = $(this);
        const tagCode = $checkbox.data('tag-code');
        if ($checkbox.prop('checked')) {
            $checkbox.closest('.list-group-item').addClass('active');
        } else {
            $checkbox.closest('.list-group-item').removeClass('active');
        }
    });
});

$('#legidoxSaveTagOrder').on('click', function () {
    const orderedTags = [];
    $('#legidoxTagSortableList .form-check-input:checked').each(function () {
        orderedTags.push($(this).data('tag-code'));
    });

    // Send orderedTags to ajax.php and update the tag order
    updateTree($treeContainer, orderedTags, true);
    $('#legidoxTagSortModal').modal('hide');
});

$('#legidox-more-tags-button').on('click', function () {
    // Clear the modal selection and Sortable.js list when opening
    $('#legidoxTagSortableList .list-group-item').removeClass('active');
    const sortable = Sortable.get(document.getElementById('legidoxTagSortableList'));
    if (sortable) {
        sortable.destroy();
    }
    $('#legidoxTagSortModal').modal('show');
});
