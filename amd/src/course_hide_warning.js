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
 * Prevents hiding exam activity quizzes on the course page.
 *
 * @module     local_examguard/course_hide_warning
 * @copyright  2026 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Alex Yeung <k.yeung@ucl.ac.uk>
 */

import ModalCancel from 'core/modal_cancel';
import {get_string as getString} from 'core/str';

/**
 * Initialise the course hide prevention module.
 *
 * @param {Array} scheduledcmids Array of cmids that are exam activities.
 */
export const init = (scheduledcmids) => {
    // Use capture phase to intercept before the course format actions handler.
    document.addEventListener('click', async(e) => {
        const target = e.target.closest('[data-action="cmHide"]');
        if (!target) {
            return;
        }

        // Do nothing if it is not an exam activity.
        const cmid = target.dataset.id;
        if (!cmid || !scheduledcmids.includes(Number(cmid))) {
            return;
        }

        // Block the hide action entirely.
        e.preventDefault();
        e.stopImmediatePropagation();

        // Show an alert modal so the user sees it regardless of scroll position.
        const [title, body] = await Promise.all([
            getString('error:cannot_hide_exam_activity_title', 'local_examguard'),
            getString('error:cannot_hide_exam_activity', 'local_examguard'),
        ]);

        const modal = await ModalCancel.create({
            title: title,
            body: body,
        });
        modal.show();
    }, true);
};
