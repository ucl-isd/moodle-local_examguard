@local @local_examguard
Feature: Ban course editing for users with editing roles from a parent context
  In order to protect exams in progress
  As an exam guard administrator
  I need editing users from the course category to be banned during the exam and released afterwards

  Background:
    Given the following "categories" exist:
      | name       | category | idnumber |
      | Category 1 | 0        | CAT1     |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | CAT1     |
    And the following "users" exist:
      | username  | firstname | lastname | email                 |
      | cateditor | Category  | Editor   | cateditor@example.com |
      | student1  | Student   | 1        | student1@example.com  |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And the following "role assigns" exist:
      | user      | role           | contextlevel | reference |
      | cateditor | manager        | Category     | CAT1      |
    And the following config values are set as admin:
      | config       | value | plugin          |
      | enabled      | 1     | local_examguard |
      | examduration | 300   | local_examguard |
      | timebuffer   | 10    | local_examguard |
    And the following "activity" exists:
      | activity  | quiz              |
      | course    | C1                |
      | name      | Exam Quiz         |
      | timeopen  | ##now -1 hour##   |
      | timeclose | ##now +1 hour##   |

  Scenario: A category editor sees the banner and cannot edit during an exam
    When I am on the "Exam Quiz" "quiz activity" page logged in as cateditor
    Then I should see "Exam in progress!"
    And I am on "Course 1" course homepage
    And I should see "Exam in progress!"
    And "Edit mode" "field" should not exist

  Scenario: A category editor can edit again once the exam has ended
    Given I am on the "Exam Quiz" "quiz activity" page logged in as cateditor
    And I should see "Exam in progress!"
    And I log out
    # The admin ends the exam by removing the quiz close time.
    And I am on the "Exam Quiz" "quiz activity editing" page logged in as admin
    And I set the field "timeclose[enabled]" to "0"
    And I press "Save and return to course"
    And I log out
    When I log in as "cateditor"
    And I am on "Course 1" course homepage
    Then I should not see "Exam in progress!"
    And "Edit mode" "field" should exist

  Scenario: A site admin sees the banner and can still edit during an exam
    When I am on the "Exam Quiz" "quiz activity" page logged in as admin
    Then I should see "Exam in progress!"
    And I am on the "Exam Quiz" "quiz activity editing" page
    And I set the field "Name" to "Exam Quiz updated"
    And I press "Save and display"
    And I should see "Exam Quiz updated"
    And I should not see "Editing is not allowed during exam."
