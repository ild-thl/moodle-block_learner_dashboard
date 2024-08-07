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
 * Helper-Module to get dynamically generated node-selectors
 * Does need to keep track of the currently implemented viewtypes
 *
 * @module      block_disealytics/view_selection
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

let viewlist = [];
let courseid = null;

/**
 * Set the viewtypes array based on the provided views. Sets them to 1 as in visible/enabled like so:
 * [{"activity-view":1},...]
 *
 * @param {Array} views - An array of views (from the project files).
 * @returns {void}
 */
export const setViewlist = (views) => {
    viewlist = views;
};

/**
 * Keeps track of every view that is currently implemented.
 * Used by the other modules to generate the view-selectors.
 * @returns {string[] | null} List of viewtypes that are currently implemented and whether or not they are enabled (1 or 0).
 * Returns null if the array is empty.
 */
export const getViewlist = () => {
    return viewlist.length > 0 ? viewlist : null;
};

/**
 * Update the view order and which views are visible in the DOM by modifying the viewtypes array.
 *
 * @param {string} modifiedView - The view to be modified.
 * @param {string} write - The write action to be performed ('delete' or other).
 * @returns {Array} - The updated viewtypes array.
 */
export const updateViewlist = (modifiedView, write) => {
    let updatedViews = [...getViewlist()];
    let index = updatedViews.findIndex((view) => view.viewname === modifiedView);
    if (index !== -1) {
        updatedViews.splice(index, 1);
        if (write === 'add') {
            const newView = {viewname: modifiedView, enabled: 1};
            updatedViews.push(newView);
        }
        if (write === 'delete') {
            const newView = {viewname: modifiedView, enabled: 0};
            updatedViews.push(newView);
        }
    }
    return updatedViews;
};

/**
 * Sets the course ID to the specified value.
 *
 * @param {number} id - The course ID to set.
 */
export const setCourseId = (id) => {
    courseid = id;
};

/**
 * Retrieves the currently set course ID.
 *
 * @returns {number} The currently set course ID.
 */
export const getCourseId = () => {
    return courseid;
};

export let oldViews = [];
// Saves the element name that a function will scroll to after a reload of a view.
export let scrollToElement = '';
// The offset to top so the elements can be seen better.
export let offsetTopForScroll = 0;
// Describes if a scroll is needed after a reload of a view.
export let scrollTo = false;

export const viewIsOld = (view) => {
    return oldViews.includes(view);
};

export const setOld = (view) => {
    if (!oldViews.includes(view)) {
        oldViews.push(view);
    }
};

export const getOffsetTopForScroll = () => {
    return offsetTopForScroll;
};

export const setOffsetTopForScroll = (offset) => {
    offsetTopForScroll = offset;
};

export const setScrollToElement = (idOfView) => {
    scrollToElement = idOfView;
};

export const getScrollTo = () => {
    return scrollTo;
};

export const setScrollTo = (bool) => {
    scrollTo = bool;
};

export const getScrollToElement = () => {
    return scrollToElement;
};

/**
 * Scrolls to an Element after triggering an event
 * @param {String} target id of the element
 * @param {Number} offset value for more offset to the topside of the element
 */
export function scrollToTargetAdjusted(target, offset) {
    // The target where the function will scroll to.
    const element = document.getElementById(target);
    // Gets the distance from top of the element.
    if (element) {
        const bodyRect = document.body.getBoundingClientRect().top;
        // Gets the distance from top of the element.
        const elementRect = element.getBoundingClientRect().top;
        // Current location of the element (top distance).
        const elementPosition = elementRect - bodyRect;
        // The offsetPosition is needed because moodle has a navigation header, and it has a higher z-index.
        // The position wouldn't be fully visible.
        const offsetPosition = elementPosition - offset;

        window.scrollTo({
            top: offsetPosition,
            behavior: 'smooth'
        });
        setScrollTo(false);
    }
}

export const unsetOld = (view) => {
    if (oldViews.includes(view)) {
        let index = oldViews.indexOf(view);
        oldViews.splice(index, 1);
    }
};

/**
 * Selectors object to access miscellaneous data-attributes
 * @type {{views: {selectEveryViewContainer: string}}}
 */
export const selectors = {
    views: {
        selectEveryViewContainer: '[data-containertype="block_disealytics/view-container"]',
    },
};

/**
 * Inserts the viewname into the data-attributes, so they can be later used as selectors
 * @param {string} viewname in the format of 'viewname-view'
 * @returns {{selectViewClass: string, selectExpandableClass: string}}
 */
export const getViewSelectors = (viewname) => {
    return {
        selectViewClass: ".block_disealytics-container-" + viewname,
        selectExpandableClass: ".block_disealytics-expandable-" + viewname
    };
};
