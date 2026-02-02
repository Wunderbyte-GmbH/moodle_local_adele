@local @local_adele @javascript

Feature: As an admin I perform editing of the adele learning plan.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname    | email                       |
      | student  | Student   | Test        | toolgenerator1@example.com  |
      | teacher  | Teacher   | Test        | toolgenerator3@example.com  |
      | manager  | Manager   | Test        | toolgenerator4@example.com  |
    And the following "courses" exist:
      | fullname | shortname | summary |
      | Course 1 | C1        | LP Course 1 |
      | Course 2 | C2        | LP Course 2 |
      | Course 3 | C3        | LP Course 3 |
      | Course 4 | C4        | LP Course 4 |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student  | C1     | student        |
      | teacher  | C1     | editingteacher |
    And the following config values are set as admin:
      | config            | value                                                      | plugin      |
      | restrictionfilter | timed,timed_duration,specific_course,parent_courses,manual | local_adele |
    And the following "local_adele > learningpaths" exist:
      | name     | description   | filepath                                               | courses     | image                                                 |
      | Test LP1 | Test LP1 Desc | local/adele/tests/fixtures/learning_plan3_courses.json | C1,C2,C3,C4 | /local/adele/public/node_background_image/image_1.jpg |
    And I change viewport size to "1366x3000"

  @javascript
  Scenario: Adele usage: admin edit a learning path created via DB
    Given I log in as "admin"
    And I click on "Learning Paths" "button" in the "#usernavigation" "css_element"
    And I should see "Learning Path 2025" in the ".learningcardcont .learningcard" "css_element"
    And I should see "Learning Path 2025 Description" in the ".learningcardcont .learningcard" "css_element"
    And I click on ".learningcardcont .learningcard" "css_element"
    And I should see "Course 1" in the ".vue-flow.learning-path-flow" "css_element"
    And I should see "Course 2" in the ".vue-flow.learning-path-flow" "css_element"
    And I should see "Course 3" in the ".vue-flow.learning-path-flow" "css_element"
    And "[data-id=\"dndnode_2dndnode_1\"]" "css_element" should exist
    And "[data-id=\"dndnode_3dndnode_2\"]" "css_element" should exist
    ## Edit completion for course 2 - add customized "manual completion" checkbox.
    And I click on "[data-id='dndnode_2'] .icon-link .fa-tasks" "css_element"
    And I wait "1" seconds
    And I drag and drop HTML5 from ".learning-path-nodes-container .nodes > :nth-child(3)" to "[data-id='condition_1']" as "[data-id='source_and']"
    And I wait "1" seconds
    And I pan vue flow to "[data-id='condition_2']"
    And I set the field "Enable Textarea" to "checked"
    And I set the field with xpath "//div[@data-id='condition_2']//textarea[@class='form-control']" to "must be completed by teacher"
    And I click on "Update Information" "button" in the "[data-id='condition_2']" "css_element"
    And I wait "1" seconds
    And I click on "Save" "button" in the ".vue-flow__panel.save-restore-controls" "css_element"
    And I wait "1" seconds
    And I click on "[data-id='dndnode_2'] .icon-link .fa-tasks" "css_element"
    And I pan vue flow to "[data-id='condition_2']"
    And I should see "Node completion checkbox" in the "[data-id='condition_2']" "css_element"
    ## TODO: Fix below step to validate textarea content (element ids/names issue?).
    ##And the field "//div[@data-id='condition_2']//textarea[@class='form-control']" matches value "must be completed by teacher"
    And I should see "AND" in the ".vue-flow__edge-labels" "css_element"
    And I press "Go Back to Learningpath"
    ## Edit restriction for course 3 manually.
    And I click on "[data-id='dndnode_3'] .icon-link .fa-lock" "css_element"
    And I wait "1" seconds
    ## TODO: not working - problems with selectors
    ##And I drag and drop HTML5 from ".learning-path-nodes-container .nodes > :nth-child(2)" to "[data-id='condition_1']" as "[data-id='source_and']"
    ##And I wait "1" seconds
    ##And I pan vue flow to "[data-id='condition_2']"
    ##And I set the field with xpath "//div[@data-id='condition_2']//select[@class='form-control']" to "Since node subscription"
    ##And I set the field with xpath "//div[@data-id='condition_2']//input[@class='form-control']" to "2"
    ##And I wait "31" seconds
    And I drag and drop HTML5 from ".learning-path-nodes-container .nodes > :nth-child(1)" to "[data-id='condition_1']" as "[data-id='source_and']"
    And I wait "1" seconds
    And I pan vue flow to "[data-id='condition_2']"
    And I set the field with xpath "//div[@data-id='condition_2']//input[@class='form-control']" to "2056-02-25T22:05"
    And I wait "1" seconds
    And I click on "Save" "button" in the ".vue-flow__panel.save-restore-controls" "css_element"
    And I wait "1" seconds
    And I click on "[data-id='dndnode_3'] .icon-link .fa-lock" "css_element"
    And I should see "According to parent nodes" in the "[data-id='condition_1']" "css_element"
    And I should see "Node start/end date" in the "[data-id='condition_2']" "css_element"
    ## TODO: Fix below step to validate textarea content (element ids/names issue?).
    ##And the field "//div[@data-id='condition_2']//input[@class='form-control']" matches value "2056-02-25T22:05"
    And I press "Go Back to Learningpath"
