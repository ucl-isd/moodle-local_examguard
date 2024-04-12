<?php
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

namespace local_examguard\output;

use plugin_renderer_base;

/**
 * Output renderer for local_examguard.
 *
 * @package    local_examguard
 * @copyright  2024 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Alex Yeung <k.yeung@ucl.ac.uk>
 */
class renderer extends plugin_renderer_base {

    /**
     * Render notification banner.
     *
     * @param int $examendtime The exam end time.
     * @return string
     * @throws \moodle_exception
     */
    public function render_notification_banner(int $examendtime): string {
        return $this->output->render_from_template(
            'local_examguard/notification_banner',
            ['examendtime' => date('h:i a', $examendtime)]
        );
    }
}
