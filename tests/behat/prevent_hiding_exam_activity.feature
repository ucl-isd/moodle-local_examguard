@local @local_examguard
Feature: Prevent hiding exam activities
  In order to protect exam activities from being accidentally hidden
  As a teacher
  I need to be prevented from hiding a future exam activity

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
      | config       | value | plugin            |
      | enabled      | 1     | local_examguard   |
      | examduration | 300   | local_examguard   |
      | timebuffer   | 10    | local_examguard   |

  @javascript
  Scenario: Cannot hide a future exam quiz via the course page action menu
    Given the following "activity" exists:
      | activity  | quiz              |
      | course    | C1                |
      | name      | Exam Quiz         |
      | timeopen  | ##now +1 hour##   |
      | timeclose | ##now +4 hours##  |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    When I open "Exam Quiz" actions menu
    And I choose "Hide" in the open action menu
    Then I should see "Cannot hide exam activity" in the ".modal-title" "css_element"
    And I should see "This activity cannot be hidden because Exam Guard treats it as an exam activity" in the ".modal-body" "css_element"

  @javascript
  Scenario: Can hide a future quiz whose duration exceeds the exam threshold via the course page action menu
    Given the following "activity" exists:
      | activity  | quiz              |
      | course    | C1                |
      | name      | Long Quiz         |
      | timeopen  | ##now +1 hour##   |
      | timeclose | ##now +8 hours##  |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    When I open "Long Quiz" actions menu
    And I choose "Hide" in the open action menu
    Then "Long Quiz" activity should be hidden
