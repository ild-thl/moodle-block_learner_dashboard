// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Renders templates depending on the current user_preferences
 *
 * @module      block_disealytics/update_view
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


import Ajax from 'core/ajax';
import Template from 'core/templates';
import {
    getViewlist,
    getViewSelectors,
    setViewlist,
    scrollToTargetAdjusted,
    setScrollToElement,
    setOffsetTopForScroll,
    getScrollTo,
    getScrollToElement,
    getOffsetTopForScroll,
    viewIsOld,
    setOld,
    unsetOld,
    selectors,
    setCourseId,
    getCourseId
} from 'block_disealytics/view_selection';
import {
    initGoalEventListeners
} from "./learning_goals_functions";
export const anyViewsEnabled = () => {
    // Initialize anyViewsEnabled to false.
    let anyViewsEnabled = false;

    // Check if any views are enabled.
    const viewList = getViewlist();
    for (const {enabled} of viewList) {
        if (enabled === 1) {
            anyViewsEnabled = true;
            // Exit the loop as soon as an enabled view is found.
            break;
        }
    }
    return anyViewsEnabled;
};

/**
 * Initializes the plugin when it first loads by rendering the main template.
 *
 * @param {Array} views - An array of views to be used by the plugin.
 * @param {string} viewmode - The current view mode.
 * @param {number} courseid - The ID of the course associated with the plugin.
 * @param {string} agreementurl - URL at which the data policy can be viewed
 */
export const init = async(views, viewmode, courseid, agreementurl) => {
    /**
     * Callback function to execute when the document is ready.
     */
    const callback = async function() {
        // Set the available views and configured views.
        setViewlist(views);
        setCourseId(courseid);

        // Render the main template with the available views and view mode.
        renderMainTemplate(getViewlist(), viewmode, agreementurl);

        // Update the view (optional parameters are undefined in this context).
        await updateView(getCourseId(), undefined);
    };

    // Check if the document is already loaded or loading and execute the callback accordingly.
    if (
        document.readyState === "complete" ||
        (document.readyState !== "loading" && !document.documentElement.doScroll)
    ) {
        // Document is already loaded, execute the callback immediately.
        await callback();
    } else {
        // Add an event listener for when the document is ready.
        document.addEventListener("DOMContentLoaded", async function() {
            await callback();
        });
    }
};


/**
 * Called once when the plugin is loaded.
 * Loads the main.mustache template.
 * @param {array} views all implemented viewtypes
 * @param {string} viewmode the viewmode to load
 * @param {string} agreementurl - URL at which the data policy can be viewed
 */
const renderMainTemplate = (views, viewmode, agreementurl) => {
    const maintemplatedata = [];
    // Use map function to receive only the 'viewname' property of the given views as 'view'.
    maintemplatedata.viewtypes = views.map(view => {
        return {
            'viewname': view.viewname,
            'enabled': (view.enabled === 1)
        };
    });
    maintemplatedata.viewmode = viewmode;
    maintemplatedata[viewmode] = true;
    maintemplatedata.agreementurl = agreementurl;

    Template.renderForPromise("block_disealytics/main", maintemplatedata)
        .then(({html, js}) => {
            Template.replaceNodeContents('.block_disealytics .content', html, js);
             return renderEditingMode('0');
        }).catch(ex => window.console.log(ex));
};

/**
 * Calls refresh_view.php to get all the data/views that should be displayed
 * renders the views that it got as a response, while erasing the ones that are still shown but shouldn't
 * @param {int} courseid the current courseid
 * @param {array} views list of views to update
 */
export const updateView = async(courseid, views) => {
    // Set views to old.
    if (views) {
        views.forEach(view => setOld(view));
    }
    let viewlist = JSON.stringify(getViewlist());

    // See, if there are any settings in the database for the viewtypes.
    Ajax.call([{
        methodname: 'block_disealytics_refresh_view',
        args: {courseid, viewlist},
    }])[0].done(async response => {
        const viewData = JSON.parse(response);
        const allViews = getViewlist();

        allViews.forEach(({viewname}) => {
            const viewSelectors = getViewSelectors(viewname);
            const viewDataExists = viewname in viewData.views;
            const currentView = document.querySelector(viewSelectors.selectViewClass);

            if (viewDataExists) {
                const viewDataToRender = viewData.views[viewname];
                renderViewTemplate(
                    viewSelectors.selectViewClass,
                    viewDataToRender,
                    viewData.editing,
                    viewData.expanded_view,
                    viewname
                );
            } else {
                if (currentView) {
                    currentView.innerHTML = "";
                }
            }
        });
        // Editing Mode.
        renderEditingMode(viewData.editing);
        renderExpandedView(viewData.expanded_view);
        if (getScrollTo()) {
            scrollToTargetAdjusted(getScrollToElement(), getOffsetTopForScroll());
        }
    }).fail(err => {
        window.console.log(err);
    });
};

/**
 * Determines whether the close-buttons inside the views and the add-view-buttons in the add-view-modal are shown
 * @param {string} isEnabled that represents whether the editing is on or off
 */
const renderEditingMode = (isEnabled) => {
    const allViewsContainer = document.querySelector(".block_disealytics-all-views-container");
    if (allViewsContainer) {
        allViewsContainer.classList.add("viewmode");
        allViewsContainer.classList.remove("editmode");
    }
    // Edit button to customize views.
    const editButton = document.querySelector("#block_disealytics_main_edit_button");
    if (editButton) {
        editButton.classList.remove("text-danger");
    }
    // All views container.
    const viewContainers = document.querySelectorAll(".view-container");
    // All views container set draggable to false when not editing.
    if (viewContainers) {
        [].forEach.call(viewContainers, (e) => {
            e.setAttribute('draggable', 'false');
            e.classList.remove('draggable');
        });
    }
    const thingsToShow = document.querySelectorAll(".show-when-editing");
    [].forEach.call(thingsToShow, (e) => {
        e.classList.add("hidden");
    });
    const thingsToHide = document.querySelectorAll(".hide-when-editing");
    [].forEach.call(thingsToHide, (e) => {
        e.classList.remove("hidden");
    });
    if (isEnabled === '1') {
        if (allViewsContainer) {
            allViewsContainer.classList.remove("viewmode");
            allViewsContainer.classList.add("editmode");
        }
        // Editbutton design settings.
        if (editButton) {
            editButton.classList.add("text-danger");
            // ToggleButton.classList.remove("fa-toggle-off");
            // toggleButton.classList.add("fa-toggle-on");
        }
        if (thingsToHide) {
            [].forEach.call(thingsToHide, (e) => {
                e.classList.add("hidden");
            });
        }
        if (thingsToShow) {
            [].forEach.call(thingsToShow, (e) => {
                e.classList.remove("hidden");
            });
        }
        // Checks if every view is used to give the user information that there is nothing to add when trying to add another one.
        let allViewTypesUsed = true;
        getViewlist().forEach(({viewname, enabled}) => {
            const viewContainer = document.querySelector('#block_disealytics-' + viewname);
            viewContainer.setAttribute('draggable', 'true');
            viewContainer.classList.add('draggable');
            if (enabled === 0) {
                allViewTypesUsed = false;
            }
            });
        // This handles the information given to the user, when all views are used or not.
        if (allViewTypesUsed) {
            document.querySelector('.show-when-viewtype-selectable').classList.add('hidden');
            document.querySelector('.show-when-all-viewtypes-used').classList.remove('hidden');
        } else {
            document.querySelector('.show-when-viewtype-selectable').classList.remove('hidden');
            document.querySelector('.show-when-all-viewtypes-used').classList.add('hidden');
        }
    }
};

/**
 * Renders the expanded part of a view, while hiding every other view
 * @param {string} viewtype that represents the view that should be expanded
 */
const renderExpandedView = (viewtype) => {
    // The two objects elementsHide and elementsShow are used for the visibility in the block section.
    const elementsHide = document.querySelectorAll('.hide-when-expanded');
    const elementsShow = document.querySelectorAll('.show-when-expanded');
    // Show all elements in the view in the block section.
    [].forEach.call(elementsHide, (e) => {
        e.classList.remove("hidden");
    });
    // Hide all elements in the view in the block section.
    [].forEach.call(elementsShow, (e) => {
        e.classList.add("hidden");
    });
    // Set every expandable windows in the views to hidden.
    const allViewContainer = document.querySelectorAll(".view-container");
    [].forEach.call(allViewContainer, (e) => {
        if (e.getAttribute('id') !== 'block_disealytics-optional-inputs-view') {
            e.classList.add("hidden");
        }
    });
    const expandableViews = document.querySelectorAll(".block_disealytics-expandable");
    [].forEach.call(expandableViews, (e) => {
        e.classList.remove("active");
    });
if (viewtype === "none") {
    const expandableDivs = document.querySelectorAll(".block_disealytics-expandable");
    [].forEach.call(expandableDivs, (e) => {
        e.classList.add("hidden");
        });
    const allViewContainer = document.querySelectorAll(selectors.views.selectEveryViewContainer);
    [].forEach.call(allViewContainer, (e) => {
        e.classList.remove("hidden");
        });
    // Sets every button to an open symbol button.
    const ToggleButton = document.querySelectorAll(".block_disealytics-toggle-expansion-btn");
    [].forEach.call(ToggleButton, (e) => {
        const buttonOpen = e.querySelector(".expandable-open");
        // On first load the buttonOpen is null. Therefore, we need an if check.
        if (buttonOpen) {
            buttonOpen.classList.remove('hidden');
        }
        const buttonClose = e.querySelector(".expandable-close");
        // On first load the buttonClose is null. Therefore, we need an if check.
        if (buttonClose) {
            buttonClose.classList.add('hidden');
        }
        });
    // The offset has to be saved temporary, because the logic of loading views has a special behaviour.
    setOffsetTopForScroll(60);
    return;
}

    // Hide all elements in the views when expanded.
    [].forEach.call(elementsHide, (e) => {
        e.classList.add("hidden");
    });
    // Show all elements in the views when expanded.
    [].forEach.call(elementsShow, (e) => {
        e.classList.remove("hidden");
    });
    // Hides every view except the expanded one.
    const viewSelectors = getViewSelectors(viewtype);
    const currentView = document.querySelector(viewSelectors.selectViewClass);
    // On first load the currentView is null. Therefore, we need an if check.
if (currentView) {
    currentView.classList.remove("hidden");
}
    const expandableView = document.querySelector(viewSelectors.selectExpandableClass);
    // On first load the expandableView is null. Therefore, we need an if check.
if (expandableView) {
    if (viewtype !== 'optional-inputs-view') {
        setScrollToElement('block_disealytics-panel-' + viewtype);
        setOffsetTopForScroll(100);
    }
    expandableView.classList.add("active");
    expandableView.classList.remove("hidden");
}
    const ButtonOpen = document.querySelector(".block_disealytics-toggle-expansion-btn-"
        + viewtype + " .expandable-open");
if (ButtonOpen) {
    ButtonOpen.classList.add('hidden');
}
    const ButtonClose = document.querySelector(".block_disealytics-toggle-expansion-btn-"
        + viewtype + " .expandable-close");
if (ButtonClose) {
    ButtonClose.classList.remove('hidden');
}
};

/**
 * Renders a view-template
 * @param {string} nodeSelector div-container-selector in which the view is located
 * @param {object} viewInfo all template-data of the view
 * @param {string} editing whether editing is turned on right now
 * @param {string} expandedView which view is currently expanded
 * @param {string} viewtype current view name
 */
const renderViewTemplate = (nodeSelector, viewInfo, editing, expandedView, viewtype) => {
    if (nodeIsEmpty(nodeSelector) || viewIsOld(viewtype)) {
        Template.renderForPromise(viewInfo.template_path, viewInfo.data)
            .then(({html, js}) => {
                Template.replaceNodeContents(nodeSelector, html, js);
                renderEditingMode(editing);
                renderExpandedView(expandedView);
                if (viewtype === 'learning-goals-view') {
                    initGoalEventListeners();
                }
                return unsetOld(viewtype);
            }).catch(ex => window.console.log(ex));
    }
};

/**
 * Helper-function to check if a node in the dom-tree is truly empty
 * @param {string} selector the node-selector, typically a data-attribute
 * @returns {boolean} whether the node is empty or not
 */
const nodeIsEmpty = (selector) => {
    if (document.querySelector(selector) === null) {
        return true;
    }
    return (document.querySelector(selector).innerHTML.trim() === "");
};
