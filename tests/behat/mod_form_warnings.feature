@local @local_examguard
Feature: Real-time warnings on the module edit form
  In order to avoid misconfiguring exam activities
  As a teacher
  I need to see warnings when hiding an activity that qualifies as an exam or has no open/close times

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following config values are set as admin:
      | config       | value | plugin          |
      | enabled      | 1     | local_examguard |
      | examduration | 300   | local_examguard |
      | timebuffer   | 10    | local_examguard |

  @javascript
  Scenario: Warning shown on edit form when hiding a future exam activity
    Given the following "activity" exists:
      | activity  | quiz             |
      | course    | C1               |
      | name      | Exam Quiz        |
      | timeopen  | ##now +1 hour##  |
      | timeclose | ##now +4 hours## |
    When I am on the "Exam Quiz" "quiz activity editing" page logged in as teacher1
    And I expand all fieldsets
    And I set the field "Availability" to "Hide on course page"
    Then I should see "Warning: This activity is hidden but Exam Guard treats it as an exam activity"

  @javascript
  Scenario: No warning shown on edit form when hiding a quiz that exceeds the exam duration threshold
    Given the following "activity" exists:
      | activity  | quiz             |
      | course    | C1               |
      | name      | Long Quiz        |
      | timeopen  | ##now +1 hour##  |
      | timeclose | ##now +8 hours## |
    When I am on the "Long Quiz" "quiz activity editing" page logged in as teacher1
    And I expand all fieldsets
    And I set the field "Availability" to "Hide on course page"
    Then I should not see "Warning: This activity is hidden but Exam Guard treats it as an exam activity"

  @javascript
  Scenario: Warning shown on edit form when hiding a quiz without open and close times
    Given the following "activity" exists:
      | activity | quiz |
      | course   | C1   |
      | name     | Quiz |
    When I am on the "Quiz" "quiz activity editing" page logged in as teacher1
    And I expand all fieldsets
    And I set the field "Availability" to "Hide on course page"
    Then I should see "We recommend that you set an open and close time"
