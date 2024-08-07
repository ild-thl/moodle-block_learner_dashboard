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

import Ajax from 'core/ajax';
import {get_string as getString} from 'core/str';
import {updateView} from "./update_view";
import {getCourseId} from "./view_selection";
import {createDiseaModal, getValueById, showDiseaModal} from "./add_interaction";

let plannerModal = null;

/**
 * Initialize the planner-view interactions.
 *
 */
export const init = async() => {
    await updateMonthDisplay();
    initDeleteEventListeners();
    initAddEventListeners();
};

/**
 * Asynchronously updates the month display in the planner view.
 * Fetches and displays the updated planner data based on the specified direction (forwards or backwards).
 *
 * @async
 * @function
 * @returns {Promise<void>} A Promise that resolves when the update is complete or rejects on error.
 *
 * @throws {Error} If there is an error during the update process.
 */
export async function updateMonthDisplay() {
    try {
        const courseId = getCourseId();
        const plannerViews = document.querySelectorAll('.block_disealytics-planner-left-side');
        plannerViews.forEach(planner => {
            const day = parseInt(planner.getAttribute('data-day'), 10);
            const month = parseInt(planner.getAttribute('data-month'), 10);
            const year = parseInt(planner.getAttribute('data-year'), 10);
            const navigateButtons = planner.querySelectorAll('.select-month-btn');

            navigateButtons.forEach(navigateBtn => {
                navigateBtn.addEventListener('click', async() => {
                    const direction = navigateBtn.classList.contains('select-next-month') ? 'forwards' : 'backwards';
                    await new Promise((resolve, reject) => {
                        Ajax.call([{
                            methodname: 'block_disealytics_change_planner_view',
                            args: {
                                courseid: courseId,
                                day: day,
                                month: month,
                                year: year,
                                direction: direction,
                            }
                        }])[0].done(() => {
                            // Resolve the promise when done.
                            resolve();
                        }).fail(err => {
                            // Reject the promise if there's an error.
                            reject(err);
                        });
                    });

                    // Update the view after saving the goal.
                    await updateView(courseId, ['planner-view']);
                });
            });
        });
    } catch (error) {
        // Handle any errors here.
        window.console.error(error);
    }
}

/**
 * Initializes event listeners for buttons in the planner form.
 * Handle changes in the planner form inputs and updates the corresponding fields accordingly.
 *
 * @function
 * @name initButtonsInPlannerForm
 * @returns {void}
 */
export function initButtonsInPlannerForm() {
    const plannerNoEnd = document.getElementById('planner-no-end-input');
    const plannerWithEnd = document.getElementById('planner-until-input');
    const plannerDuration = document.getElementById('planner-duration-input');
    const plannerRepetitions = document.getElementById('planner-event-repetitions-input-checkbox');

    if (plannerNoEnd) {
        plannerNoEnd.addEventListener('change', () => {
            document.getElementById('planner-date-end-input').disabled = true;
            document.getElementById('planner-time-end-input').disabled = true;
            document.getElementById('planner-duration-text').disabled = true;
        });
    }

    if (plannerWithEnd) {
        plannerWithEnd.addEventListener('change', () => {
            document.getElementById('planner-date-end-input').disabled = false;
            document.getElementById('planner-time-end-input').disabled = false;
            document.getElementById('planner-duration-text').disabled = true;
        });
    }

    if (plannerDuration) {
        plannerDuration.addEventListener('change', () => {
            document.getElementById('planner-date-end-input').disabled = true;
            document.getElementById('planner-time-end-input').disabled = true;
            document.getElementById('planner-duration-text').disabled = false;
        });
    }

    if (plannerRepetitions) {
        plannerRepetitions.addEventListener('change', () => {
            document.getElementById('planner-event-repetitions-input').disabled = !this.checked;
        });
    }
}

/**
 * Initializes event listeners for the planner form, including buttons for adding, saving and
 * canceling events. Also, handles interactions with the planner form inputs and the database.
 *
 * @function
 * @name initPlannerEventListeners
 * @returns {void}
 */
export function initPlannerEventListeners() {
    // The button to add goals.
    const saveDateBtns = document.querySelectorAll('.save-planner-date');
    const cancelDateBtns = document.querySelectorAll('.cancel-planner-date');

    // Saving event in database.
    if (saveDateBtns) {
        saveDateBtns.forEach((saveDateBtn) => {
            saveDateBtn.addEventListener('click', async function() {
                if (await addEventToPlanner()) {
                    cancelDateBtns.forEach((cancelDateBtn) => {
                        cancelDateBtn.click();
                    });
                }
            });
        });
    }

    // Cancel/reset the create an event form.
    if (cancelDateBtns) {
        cancelDateBtns.forEach((cancelDateBtn) => {
            cancelDateBtn.addEventListener('click', async function() {
                resetPlannerEventForm();
            });
        });
    }
}

/**
 * Initializes event listeners for deleting events.
 *
 * @function
 * @name initDeleteEventListeners
 * @returns {void}
 */
export function initDeleteEventListeners() {
    const deleteEventBtns = document.querySelectorAll('.delete-event-from-planner');

    // Delete an event from the database.
    if (deleteEventBtns) {
        for (const deleteDateBtn of deleteEventBtns) {
            deleteDateBtn.addEventListener('click', async function() {
                // Extract the numeric part from the button's ID.
                const dateid = this.id.match(/\d+$/)[0];
                // Call the deleteEvent function with the extracted dateid.
                await deleteEventFromPlanner(dateid);
            });
        }
    }
}

/**
 * Initializes event listeners for adding events.
 *
 * @function
 * @name initAddEventListeners
 * @returns {void}
 */
export function initAddEventListeners() {
    const addNewEventBtns = document.querySelectorAll('.block_disealytics-add-new-event-to-planner');
    if (addNewEventBtns) {
        addNewEventBtns.forEach((addNewEventBtn) => {
            addNewEventBtn.addEventListener('click', async function() {
                plannerModal = await createDiseaModal(
                    'block_disealytics/planner_add_event_modal',
                    getString('planner_add_event_modal', 'block_disealytics'),
                    1);

                // Assuming showDiseaModal returns a promise.
                await showDiseaModal(plannerModal);

                // Add a delay to ensure the modal is fully shown (you may adjust the duration).
                await new Promise(resolve => setTimeout(resolve, 500));

                const clickedDate = this.getAttribute('data-date');
                populateDateInputs(clickedDate);
                initButtonsInPlannerForm();
                initPlannerEventListeners();
            });
        });
    }
}

/**
 * Populates date and time inputs in the planner form based on the provided date string.
 *
 * @function
 * @name populateDateInputs
 * @param {string} clickedDateInput - The date string in the format 'YYYY/MM/DD'.
 * @returns {void}
 */
export function populateDateInputs(clickedDateInput) {
    const [year, month, day] = clickedDateInput.split('/');
    const dateStartInput = document.getElementById('planner-date-start-input');
    const timeStartInput = document.getElementById('planner-time-start-input');
    const dateEndInput = document.getElementById('planner-date-end-input');
    const timeEndInput = document.getElementById('planner-time-end-input');

    const clickedDate = `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;
    const now = new Date();
    const currentTime = `${now.getHours().toString().padStart(2, '0')}:${now.getMinutes().toString().padStart(2, '0')}`;

    dateStartInput.value = clickedDate;

    dateEndInput.value = clickedDate;

    timeStartInput.value = currentTime;
    timeEndInput.value = currentTime;
}

/**
 * Resets the inputs and selections in the planner event form to their default values.
 * Also, hides any information messages related to the form.
 *
 * @function
 * @name resetPlannerEventForm
 * @returns {void}
 */
export function resetPlannerEventForm() {
    // Reset Name Input.
    document.getElementById('planner-event-name-input').value = '';

    // Reset Date and Time Inputs.
    const now = new Date();
    // eslint-disable-next-line max-len
    const currentDate = `${now.getFullYear()} - ${(now.getMonth() + 1).toString().padStart(2, '0')} - ${now.getDate().toString().padStart(2, '0')}`;
    const currentTime = `${now.getHours().toString().padStart(2, '0')}:${now.getMinutes().toString().padStart(2, '0')}`;
    document.getElementById('planner-date-start-input').value = currentDate;
    document.getElementById('planner-time-start-input').value = currentTime;
    document.getElementById('planner-date-end-input').value = currentDate;
    document.getElementById('planner-time-end-input').value = currentTime;

    // Reset Location Input.
    document.getElementById('planner-event-location-input').value = '';

    // Reset Duration Input.
    document.getElementById('planner-duration-text').value = '90';

    // Reset Radio Buttons.
    document.getElementById('planner-no-end-input').checked = true;
    document.getElementById('planner-until-input').checked = false;
    document.getElementById('planner-duration-input').checked = false;

    // Reset Checkbox.
    document.getElementById('planner-event-repetitions-input-checkbox').checked = false;
    document.getElementById('planner-event-repetitions-input').value = '1';

    // Reset Info Messages.
    document.getElementById('planner-name-invalid').classList.add('hidden');
    document.getElementById('planner-name-missing').classList.add('hidden');
    document.getElementById('planner-date-invalid').classList.add('hidden');
}

/**
 * Combines date and time values from specified HTML elements to create a JavaScript Date object.
 *
 * @function
 * @name getCombinedDateTime
 * @param {string} dateId - The ID of the HTML element containing the date value.
 * @param {string} timeId - The ID of the HTML element containing the time value.
 * @returns {Date} - The JavaScript Date object representing the combined date and time.
 */
export function getCombinedDateTime(dateId, timeId) {
    const date = document.getElementById(dateId).value;
    const time = document.getElementById(timeId).value;
    const combinedDateTimeString = `${date}T${time}`;
    return new Date(combinedDateTimeString);
}

/**
 * Asynchronously adds an event to the planner by retrieving input values,
 * performing validation, and making an AJAX call to update the planner data.
 *
 * @async
 * @function
 * @name addEventToPlanner
 * @returns {Promise<boolean>} - A promise that resolves when the event is successfully added to the planner.
 * @throws {Error} - Throws an error if there is an issue during the process.
 */
export async function addEventToPlanner() {
    try {
        const name = getValueById('planner-event-name-input');
        const location = getValueById('planner-event-location-input');
        const courseid = getCourseId();
        const eventType = getValueById('planner-event-type-input');
        const startDate = getCombinedDateTime('planner-date-start-input', 'planner-time-start-input');
        const endDate = getCombinedDateTime('planner-date-end-input', 'planner-time-end-input');
        const duration = getValueById('planner-duration-text');
        const repetitionsCheckbox = document.getElementById('planner-event-repetitions-input-checkbox');
        const repetitions = repetitionsCheckbox.checked ? getValueById('planner-event-repetitions-input') : 0;

        const timestampStart = Math.floor(new Date(startDate).getTime() / 1000);
        let calculatedDuration = 0;

        // Validation with end date.
        if (document.getElementById('planner-until-input').checked) {
            const timestampEnd = Math.floor(new Date(endDate).getTime() / 1000);
            switch (validateInputs(name, location, startDate, endDate, null, repetitionsCheckbox, repetitions)) {
                case 'correct':
                    // Calculate duration in seconds if endDate is set.
                    calculatedDuration = timestampEnd - timestampStart;
                    await new Promise((resolve, reject) => {
                        Ajax.call([{
                            methodname: 'block_disealytics_update_planner_event',
                            args: {
                                updatetype: 'add',
                                dateid: null,
                                name: name,
                                courseid: courseid,
                                timestart: timestampStart,
                                timeduration: calculatedDuration,
                                location: location === null ? '' : location,
                                eventtype: eventType === null ? '' : eventType,
                                repetitions: repetitions
                            }
                        }])[0].done(() => {
                            // Resolve the promise when done.
                            resolve();
                        }).fail(err => {
                            // Reject the promise if there's an error.
                            reject(err);
                        });
                    });
                    // Update the view after saving the event.
                    await updateView(getCourseId(), ['planner-view']);
                    return true;
                case 'namemissing':
                    await showMessage(document.getElementById('planner-name-missing'));
                    return false;
                case 'nameinvalid':
                    await showMessage(document.getElementById('planner-name-invalid'));
                    return false;
                case 'dateinvalid':
                    await showMessage(document.getElementById('planner-date-invalid'));
                    return false;
                case 'repetitionsinvalid':
                    await showMessage(document.getElementById('planner-repetition-invalid'));
                    return false;
            }
        } else if (document.getElementById('planner-duration-input').checked) {
            // Validation with duration set.
            switch (validateInputs(name, location, startDate, null, duration, repetitionsCheckbox, repetitions)) {
                case 'correct':
                    calculatedDuration = parseInt(duration, 10) * 60;
                    await new Promise((resolve, reject) => {
                        Ajax.call([{
                            methodname: 'block_disealytics_update_planner_event',
                            args: {
                                updatetype: 'add',
                                dateid: null,
                                name: name,
                                courseid: courseid,
                                timestart: timestampStart,
                                timeduration: calculatedDuration,
                                location: location === null ? '' : location,
                                eventtype: eventType === null ? '' : eventType,
                                repetitions: repetitions
                            }
                        }])[0].done(() => {
                            // Resolve the promise when done.
                            resolve();
                        }).fail(err => {
                            // Reject the promise if there's an error.
                            reject(err);
                        });
                    });
                    // Update the view after saving the event.
                    await updateView(getCourseId(), ['planner-view']);
                    return true;
                case 'namemissing':
                    await showMessage(document.getElementById('planner-name-missing'));
                    return false;
                case 'nameinvalid':
                    await showMessage(document.getElementById('planner-name-invalid'));
                    return false;
                case 'dateinvalid':
                    await showMessage(document.getElementById('planner-date-invalid'));
                    return false;
                case 'repetitionsinvalid':
                    await showMessage(document.getElementById('planner-repetition-invalid'));
                    return false;
            }
        } else {
            switch (validateInputs(name, location, startDate, null, null, repetitionsCheckbox, repetitions)) {
                case 'correct':
                    await new Promise((resolve, reject) => {
                        Ajax.call([{
                            methodname: 'block_disealytics_update_planner_event',
                            args: {
                                updatetype: 'add',
                                dateid: null,
                                name: name,
                                courseid: courseid,
                                timestart: timestampStart,
                                timeduration: calculatedDuration,
                                location: location === null ? '' : location,
                                eventtype: eventType === null ? '' : eventType,
                                repetitions: repetitions
                            }
                        }])[0].done(() => {
                            // Resolve the promise when done.
                            resolve();
                        }).fail(err => {
                            // Reject the promise if there's an error.
                            reject(err);
                        });
                    });
                    // Update the view after saving the event.
                    await updateView(getCourseId(), ['planner-view']);
                    return true;
                case 'namemissing':
                    await showMessage(document.getElementById('planner-name-missing'));
                    return false;
                case 'nameinvalid':
                    await showMessage(document.getElementById('planner-name-invalid'));
                    return false;
                case 'dateinvalid':
                    await showMessage(document.getElementById('planner-date-invalid'));
                    return false;
                case 'repetitionsinvalid':
                    await showMessage(document.getElementById('planner-repetition-invalid'));
                    return false;
            }
        }
    } catch (error) {
        window.console.error(error);
    }
    return false;
}

/**
 * Deletes an event from the planner by making an AJAX call to update the planner data.
 *
 * @async
 * @function
 * @name deleteEventFromPlanner
 * @param {string} id - The ID of the event to be deleted.
 * @throws {Error} - Throws an error if there is an issue during the deletion process.
 */
export function deleteEventFromPlanner(id) {
    Ajax.call([{
        methodname: 'block_disealytics_update_planner_event',
        args: {
            updatetype: 'delete',
            dateid: id,
        }
    }])[0].done(async function() {
        await updateView(getCourseId(), ['planner-view']);
    }).fail(err => {
        window.console.log(err);
    });
}

/**
 * Validates input values for a planner event, checking for required fields and format.
 *
 * @function
 * @name validateInputs
 * @param {string} name - The name of the planner event.
 * @param {string} location - The location of the planner event.
 * @param {number} startDate - The start date of the planner event in milliseconds since the epoch.
 * @param {number | null} endDate - The end date of the planner event in milliseconds since the epoch, or null if no end date.
 * @param {number | null} duration - The duration of the planner event in minutes, or null if no duration.
 * @param {boolean} repCheckbox - Indicates if the repetition checkbox is checked.
 * @param {number | null} repetitions - The number of repetitions for the event, or null if no repetitions.
 * @returns {string} - True if all inputs are valid, otherwise false.
 */
export function validateInputs(name,
                               location,
                               startDate,
                               endDate = null,
                               duration = null,
                               repCheckbox = false,
                               repetitions = null) {
    const eventsInvalid = document.getElementsByClassName('planner-invalid');

    for (const event of eventsInvalid) {
        event.classList.add('hidden');
    }

    if (name.trim() === '') {
        return 'namemissing';
    }

    if (!/^[a-zA-Z\d\s!?äöüÄÖÜßéè@./\-:]+$/.test(name)) {
        return 'nameinvalid';
    }

    if (location.trim() !== '' && !/^[a-zA-Z\d\s!?äöüÄÖÜßéè@./\-:]+$/.test(location)) {
        return 'nameinvalid';
    }

    if (!startDate || isNaN(startDate)) {
        return 'dateinvalid';
    }

    if (endDate !== null) {
        if (!endDate || isNaN(endDate) || endDate <= startDate) {
            return 'dateinvalid';
        }
    }

    if (duration !== null) {
        // Explicitly convert duration to a number.
        const durationNumber = Number(duration);
        if (isNaN(durationNumber)) {
            return 'dateinvalid';
        }
    }

    if (repCheckbox && (repetitions !== null)) {
        const repNumber = Number(repetitions);
        if (isNaN(repNumber)) {
            return 'repetitionsinvalid';
        }
    }
    return 'correct';
}

/**
 * Displays a message element for a short duration and then hides it.
 *
 * @param {HTMLElement} messageElement - The HTML element representing the message.
 * @returns {Promise<void>} A Promise that resolves after the message is hidden.
 */
const showMessage = async(messageElement) => {
    messageElement.classList.remove('hidden');

    await new Promise(resolve => setTimeout(resolve, 2000));

    messageElement.classList.add('hidden');
};


