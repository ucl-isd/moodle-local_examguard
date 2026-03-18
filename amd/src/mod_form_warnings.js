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
 * Real-time warnings for the exam activity module edit form.
 *
 * @module     local_examguard/mod_form_warnings
 * @copyright  2026 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Alex Yeung <k.yeung@ucl.ac.uk>
 */

import {get_string as getString} from 'core/str';

// Get unix timestamp from a date_time_selector field, or 0 if disabled.
const getFieldTimestamp = (fieldname) => {
    const enabled = document.getElementById(`id_${fieldname}_enabled`);
    if (!enabled || !enabled.checked) {
        return 0;
    }
    const part = (suffix, fallback) => {
        const el = document.getElementById(`id_${fieldname}_${suffix}`);
        return el ? parseInt(el.value) : fallback;
    };
    return Math.floor(
        new Date(part('year', 1970), part('month', 1) - 1, part('day', 1), part('hour', 0), part('minute', 0)).getTime() / 1000
    );
};

// Update warning visibility based on current form values.
const updateWarnings = (params, hiddenwarning, notimeswarning) => {
    const visibleselect = document.getElementById('id_visible');
    const visible = visibleselect ? parseInt(visibleselect.value) : 1;
    const starttime = getFieldTimestamp(params.startfieldname);
    const endtime = getFieldTimestamp(params.endfieldname);
    const now = Math.floor(Date.now() / 1000);

    // Show hidden exam warning when activity is hidden and qualifies as a future exam activity.
    const isHiddenExam = (
        visible === 0 &&
        starttime > 0 &&
        (endtime - starttime) <= params.examduration &&
        endtime > now
    );
    hiddenwarning.classList.toggle('d-none', !isHiddenExam);

    // Show hide-without-times warning when activity is hidden and has no open or close time set.
    const isHiddenWithoutTimes = visible === 0 && (starttime === 0 || endtime === 0);
    notimeswarning.classList.toggle('d-none', !isHiddenWithoutTimes);
};

// Find the form row element for a given field name using Moodle's fitem ID pattern.
const getFitem = (fieldname) => document.getElementById(`fitem_id_${fieldname}`)
    || document.getElementById(`fgroup_id_${fieldname}`);

// Create and insert a warning div before the form row of the given field.
const createWarning = (message, fieldname) => {
    const div = document.createElement('div');
    div.className = 'alert alert-warning d-none';
    div.textContent = message;
    const fitem = getFitem(fieldname);
    if (fitem) {
        fitem.parentNode.insertBefore(div, fitem);
    }
    return div;
};

/**
 * Initialise the mod form warnings module.
 *
 * @param {object} params
 * @param {string} params.startfieldname
 * @param {string} params.endfieldname
 * @param {number} params.examduration
 */
export const init = async(params) => {
    const visibleelement = document.getElementById('id_visible');
    const startenabled = document.getElementById(`id_${params.startfieldname}_enabled`);
    const endenabled = document.getElementById(`id_${params.endfieldname}_enabled`);

    // Do nothing if the form doesn't have the expected fields.
    if (!visibleelement || !startenabled || !endenabled) {
        return;
    }

    // Get the warning strings.
    const [hiddenmsg, notimesmsg] = await Promise.all([
        getString('warning:hidden_exam_activity', 'local_examguard'),
        getString('warning:hide_without_times', 'local_examguard'),
    ]);

    const hiddenwarning = createWarning(hiddenmsg, 'visible');
    const notimeswarning = createWarning(notimesmsg, 'visible');

    // Run initial check.
    updateWarnings(params, hiddenwarning, notimeswarning);

    // Watch all relevant fields via a single delegated listener on the form.
    const form = visibleelement.closest('form');
    if (form) {
        form.addEventListener('change', (e) => {
            const id = e.target.id;
            if (
                id === 'id_visible' ||
                id.startsWith(`id_${params.startfieldname}_`) ||
                id.startsWith(`id_${params.endfieldname}_`)
            ) {
                updateWarnings(params, hiddenwarning, notimeswarning);
            }
        });
    }
};
