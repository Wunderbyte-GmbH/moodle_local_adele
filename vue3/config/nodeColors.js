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
 *
 * @package     local_adele
 * @author      Christian Badusch
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/*
 * Contrast (#575 B6, WCAG 1.4.11 Non-text Contrast, AA).
 *
 * Measured against the card background #efefef, the three state colours were
 * below the required 3:1 and have been darkened by the smallest step that
 * clears it, hue and saturation unchanged:
 *
 *   progress / "not finished"  #db8d31 -> #c37922   2.32 -> 3.00
 *   progress / "finished"      #63aa43 -> #59993c   2.48 -> 3.02
 *   progress / case 0          #90b6ca -> #5891af   1.88 -> 3.00
 *
 * The pastel header backgrounds are NOT touched: they carry black text and
 * already reach 7.2:1 to 17.3:1 (1.4.3), and they no longer carry the state
 * on their own since every node also names its state (B1).
 */
export const progressBarColorCase0 = '#5891af';  
export const progressBarColorCaseA1 = '#c37922';
export const progressBarColorCaseA2 = '#c37922';  
export const progressBarColorCaseB = '#265471';  
export const progressBarColorCaseC = '#59993c'; 
export const progressBarColorCaseD = '#59993c';   
export const progressBarColorCaseE = '#59993c';   
export const progressBarColorCaseF = '#cc0000';
export const progressBarColorCaseDefault = '#808080';

// Header background colors
export const headerBackgroundColorCase0 = '#cae0ea';
export const headerBackgroundColorCaseA1 = '#eaddce';
export const headerBackgroundColorCaseA2 = '#eaddce';
export const headerBackgroundColorCaseB = '#6f9eb2';
export const headerBackgroundColorCaseC = '#e0edd9';
export const headerBackgroundColorCaseD = '#e0edd9';
export const headerBackgroundColorCaseE = '#e0edd9';
export const headerBackgroundColorCaseF = '#f2dfdf';
export const headerBackgroundColorCaseDefault = '#808080';

export const nodeBackgroundColorDefault = '#cccccc';
export const cardBackgroundColor = '#efefef';

export const courseNodeFinishedColor = '#59993c';
export const courseNodeNotFinishedColor = '#c37922';

export const courseNodeFinishedColorLight = '#e0edd9';
export const courseNodeNotFinishedColorLight = '#eaddce';