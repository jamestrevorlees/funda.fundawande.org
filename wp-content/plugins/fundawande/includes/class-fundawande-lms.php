<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // security check, don't load file outside WP
}


/**
 * FundaWande Lms Class
 *
 * All functionality pertaining to the custom LMS functionality of Funda Wande.
 *
 * @package Core
 * @author Pango
 *
 * @since 1.0.00
 */
class FundaWande_Lms {

    /**
     * Constructor
     */
    public function __construct() {

        add_action('FUNDAWANDE_AJAX_HANDLER_fw_lesson_complete', array( $this, 'fw_lesson_complete'));
        add_action('FUNDAWANDE_HANDLER_nopriv_fw_lesson_complete', array( $this, 'fw_lesson_complete'));

        //  This hook is fired after a lesson quiz has been graded and the lesson status is 'passed' OR 'graded'
        add_action( 'sensei_user_lesson_end', array( $this, 'fw_quiz_complete'),10,2);
        // do_action( 'sensei_user_lesson_end', $user_id, $lesson_id );

        // Fires the end of the submit_answers_for_grading function. It will fire irrespective of the submission results.
        add_action( 'sensei_user_quiz_submitted', array( $this, 'fw_quiz_submitted'),10,5);
        // do_action( 'sensei_user_quiz_submitted', $user_id, $quiz_id, $grade, $quiz_pass_percentage, $quiz_grade_type );

    }
    /**
     * Complete lesson functionality to track a lesson as complete
     *
     * @return $comment_id return the comment ID of the completed progress indicator
     */
    public function fw_lesson_complete() {
        //log the information
        if (isset($_POST)) {
            $user_id = $_POST['userid'];
            $post_id = $_POST['postid'];
            $lesson_key = $_POST['lessonkey'];

            // Run normal Sensei update logic
            $activity_logged = WooThemes_Sensei_Utils::update_lesson_status($user_id, $post_id, 'complete');

            // Determine if an existing review exists and assign
            $current_status_args = array(
                'number' => 1,
                'type' => 'fw_sub_unit_progress',
                'user_id' => $user_id,
                'status' => $lesson_key,
            );

            // possibly returns array, we just want one object
            $user_lesson_status = get_comments($current_status_args);
            if (is_array($user_lesson_status) && 1 == count($user_lesson_status)) {
                $user_lesson_status = array_shift($user_lesson_status);

            }

            // If no current review then return the review form object with user and post details
            if (empty($user_lesson_status)) {
                $time = current_time('mysql');
                $user = $user = get_userdata($user_id);
                $data = array(
                    'comment_type' => 'fw_sub_unit_progress',
                    'user_id' => $user_id,
                    'comment_date' => $time,
                    'comment_approved' => $lesson_key,
                    'comment_karma' => 1,
                    'comment_author' => $user->display_name,
                    'comment_author_email' => $user->user_email

                );

                $comment_id = wp_insert_comment($data);

            } else {
                $comment = array();
                $comment['comment_ID'] = $user_lesson_status->comment_ID;
                $comment['comment_approved'] = $lesson_key;
                $comment['comment_karma'] = 1;
                wp_update_comment( $comment );

                $comment_id = $user_lesson_status->comment_ID;
            }

            $course_id = Sensei()->lesson->get_course_id( $post_id );


            // Update user course progress
            $this->fw_update_course_progress_overall($user_id, $course_id);
            $this->fw_modules_status_of_sub_unit($user_id, $post_id);


            return $comment_id;
        }


    } // end fw_lesson_complete

    /**
     * Quiz submitted functionality to track a quiz as submitted but not graded
     *
     * @return $comment_id return the comment ID of the completed progress indicator
     */
    public function fw_quiz_submitted($user_id, $quiz_id, $grade, $quiz_pass_percentage, $quiz_grade_type) {
        //log the information

        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        $post_id = absint(get_post_meta($quiz_id, '_quiz_lesson', true));
        $lesson_key = get_post_meta($post_id, 'fw_unique_key',true);


        // Determine if an existing review exists and assign
        $current_status_args = array(
            'number' => 1,
            'type' => 'fw_sub_unit_progress',
            'user_id' => $user_id,
            'status' => $lesson_key,
        );

        // possibly returns array, we just want one object
        $user_lesson_status = get_comments($current_status_args);
        if (is_array($user_lesson_status) && 1 == count($user_lesson_status)) {
            $user_lesson_status = array_shift($user_lesson_status);

        }

        // If no current review then return the review form object with user and post details
        if (empty($user_lesson_status)) {
            $time = current_time('mysql');
            $user = $user = get_userdata($user_id);
            $data = array(
                'comment_type' => 'fw_sub_unit_progress',
                'user_id' => $user_id,
                'comment_date' => $time,
                'comment_approved' => $lesson_key,
                'comment_karma' => 0,
                'comment_author' => $user->display_name,
                'comment_author_email' => $user->user_email

            );

            $comment_id = wp_insert_comment($data);
            update_comment_meta( $comment_id, 'quiz_grade',  $grade );


        } else {
            $comment = array();
            $comment['comment_ID'] = $user_lesson_status->comment_ID;
            $comment['comment_approved'] = $lesson_key;
            $comment['comment_karma'] = 0;
            wp_update_comment( $comment );

            $comment_id = $user_lesson_status->comment_ID;
            update_comment_meta( $comment_id, 'quiz_grade',  $grade );

        }

        $course_id = Sensei()->lesson->get_course_id( $post_id );


        // Update user course progress
        $this->fw_update_course_progress_overall($user_id, $course_id);
        $this->fw_modules_status_of_sub_unit($user_id, $post_id);


        return $comment_id;



    } // end fw_quiz_submitted

    /**
     * Quiz graded functionality to track a quiz as graded
     *
     * @return $comment_id return the comment ID of the completed progress indicator
     */
    public function fw_quiz_complete($user_id, $lesson_id) {
        //log the information

        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        $post_id = $lesson_id;
        $lesson_key = get_post_meta($post_id, 'fw_unique_key',true);


        // Determine if an existing review exists and assign
        $current_status_args = array(
            'number' => 1,
            'type' => 'fw_sub_unit_progress',
            'user_id' => $user_id,
            'status' => $lesson_key,
        );

        // possibly returns array, we just want one object
        $user_lesson_status = get_comments($current_status_args);
        if (is_array($user_lesson_status) && 1 == count($user_lesson_status)) {
            $user_lesson_status = array_shift($user_lesson_status);

        }

        // If no current review then return the review form object with user and post details
        if (empty($user_lesson_status)) {
            $time = current_time('mysql');
            $user = $user = get_userdata($user_id);
            $data = array(
                'comment_type' => 'fw_sub_unit_progress',
                'user_id' => $user_id,
                'comment_date' => $time,
                'comment_approved' => $lesson_key,
                'comment_karma' => 1,
                'comment_author' => $user->display_name,
                'comment_author_email' => $user->user_email

            );

            $comment_id = wp_insert_comment($data);


        } else {
            $comment = array();
            $comment['comment_ID'] = $user_lesson_status->comment_ID;
            $comment['comment_approved'] = $lesson_key;
            $comment['comment_karma'] = 1;
            wp_update_comment( $comment );

            $comment_id = $user_lesson_status->comment_ID;

        }

        $course_id = Sensei()->lesson->get_course_id( $post_id );


        // Update user course progress
        $this->fw_update_course_progress_overall($user_id, $course_id);
        $this->fw_modules_status_of_sub_unit($user_id, $post_id);


        return $comment_id;



    } // end fw_quiz_submitted

    /**
     * Update a users course progress
     *
     * @return $course_progress return the course progress of the user
     */
    public function fw_update_course_progress_overall($user_id = null,$course_id) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        // Get course lessons
        $course_lessons = Sensei()->course->course_lessons($course_id);

        // Get lessons the user has completed
        global $wpdb;
        $query = "SELECT * FROM $wpdb->comments 
        WHERE comment_type = 'fw_sub_unit_progress' 
        AND user_id IN ($user_id) 
        AND comment_karma = 1";
        $user_lesson_status = $wpdb->get_results( $query );

        if ($user_lesson_status) {
            $course_progress = (count($user_lesson_status)/count($course_lessons)) * 100;
        } else {
            $course_progress = 0;
        }

        update_user_meta($user_id,'fw_course_progress',$course_progress);

        return $course_progress;

    }

    /**
     * Check lesson modules and their stats
     *
     * @return $changed return true if a module or unit was updated
     */
    public function fw_modules_status_of_sub_unit($user_id = null, $lesson_id) {

        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $changed = false;

        $lesson_unit = Sensei()->modules->get_lesson_module($lesson_id);
        $lesson_unit_key = get_term_meta($lesson_unit->term_id, 'fw_unique_key',true);
        error_log($lesson_unit_key);

        $lesson_module_id = $lesson_unit->parent;
        $lesson_module_key = get_term_meta($lesson_module_id, 'fw_unique_key',true);
        error_log($lesson_module_key);
        $lesson_nav = $this->fw_get_prev_next_lessons($lesson_id);

        if (!empty($lesson_nav['next'])) {
            $next_lesson_id = $lesson_nav['next'];

            $next_lesson_unit = Sensei()->modules->get_lesson_module($next_lesson_id);
            $next_lesson_unit_key = get_term_meta($next_lesson_unit->term_id, 'fw_unique_key', true);

            $next_lesson_module_id = $next_lesson_unit->parent;
            $next_lesson_module_key = get_term_meta($next_lesson_module_id, 'fw_unique_key', true);
        } else {
            $next_lesson_unit_key = '';
            $next_lesson_module_key = '';

        }

        if ($lesson_unit_key !== $next_lesson_unit_key) {
            // complete the current unit
            $changed = true;
            $this->fw_unit_complete($lesson_unit_key,$user_id);

            // Set current unit to the next lesson unit
            update_user_meta($user_id,'fw_current_unit',$next_lesson_unit_key);
            // Set current unit progress to 0, as we are in a new unit
            update_user_meta($user_id,'fw_current_unit_progress',0);



        } else {
            $unit_progress = $this->fw_unit_progress_at_lesson($lesson_id);
            update_user_meta($user_id,'fw_current_unit_progress',$unit_progress);
            update_user_meta($user_id,'fw_current_unit',$lesson_unit_key);


        }

        if ($lesson_module_key !== $next_lesson_module_key) {
            // complete the current module
            $changed = true;
            $this->fw_module_complete($lesson_module_key,$user_id);

            // Set current module to the next lesson unit
            update_user_meta($user_id,'fw_current_module',$next_lesson_module_key);
            // Set current module progress to 0, as we are in a new module
            update_user_meta($user_id,'fw_current_module_progress',0);
        } else {
            // get lesson unit to determine term ID
            $unit = Sensei()->modules->get_lesson_module($lesson_id);
            $module_progress = $this->fw_module_progress_at_unit($unit->term_id);

            update_user_meta($user_id,'fw_current_module_progress',$module_progress);
            update_user_meta($user_id,'fw_current_module',$lesson_module_key);

            $course_module_progress = $this->fw_course_progress_at_module($unit->parent);
            update_user_meta($user_id,'fw_course_module_progress',$course_module_progress);



        }

        return $changed;


    }

    /**
     * Complete unit functionality to track a unit as complete
     *
     * @return $comment_id return the comment ID of the completed progress indicator
     */
    public function fw_unit_complete($unit_id, $user_id) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        // Determine if an existing unit status exists
        $current_status_args = array(
            'number' => 1,
            'type' => 'fw_unit_progress',
            'user_id' => $user_id,
            'status' => $unit_id,
        );

        // possibly returns array, we just want one object
        $user_unit_status = get_comments($current_status_args);
        if (is_array($user_unit_status) && 1 == count($user_unit_status)) {
            $user_unit_status = array_shift($user_unit_status);

        }

        // If no current review then return the review form object with user and post details
        if (empty($user_unit_status)) {
            $time = current_time('mysql');
            $user = $user = get_userdata($user_id);
            $data = array(
                'comment_type' => 'fw_unit_progress',
                'user_id' => $user_id,
                'comment_date' => $time,
                'comment_approved' => $unit_id,
                'comment_karma' => 1,
                'comment_author' => $user->display_name,
                'comment_author_email' => $user->user_email

            );

            $comment_id = wp_insert_comment($data);

        } else {
            $comment = array();
            $comment['comment_ID'] = $user_unit_status->comment_ID;
            $comment['comment_approved'] = $unit_id;
            $comment['comment_karma'] = 1;
            wp_update_comment( $comment );

            $comment_id = $user_unit_status->comment_ID;
        }

        return $comment_id;


    } // end fw_unit_complete


    /**
     * Complete unit functionality to track a unit as complete
     *
     * @return $comment_id return the comment ID of the completed progress indicator
     *
     * TODO replace hard coded course ID
     */
    public function fw_unit_progress_at_lesson($lesson_id) {
        $unit = Sensei()->modules->get_lesson_module($lesson_id);

        $course_id = Sensei()->lesson->get_course_id( $lesson_id);


        $unit_lessons = Sensei()->modules->get_lessons( $course_id , $unit->term_id);

        $completed = 0;
        $total = 0;

        foreach ($unit_lessons as $unit_lesson) {
            $total++;

            if ($unit_lesson->ID == $lesson_id) {
                $completed = $total;
            }
        }

        $unit_progress = ($completed/$total) * 100;

        return $unit_progress;


    } // end fw_unit_progress


    /**
     * Complete unit functionality to track a unit as complete
     *
     * @return $comment_id return the comment ID of the completed progress indicator
     */
    public function fw_module_complete($module_id, $user_id) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        // Determine if an existing unit status exists
        $current_status_args = array(
            'number' => 1,
            'type' => 'fw_module_progress',
            'user_id' => $user_id,
            'status' => $module_id,
        );

        // possibly returns array, we just want one object
        $user_module_status = get_comments($current_status_args);
        if (is_array($user_module_status) && 1 == count($user_module_status)) {
            $user_module_status = array_shift($user_module_status);

        }

        // If no current review then return the review form object with user and post details
        if (empty($user_module_status)) {
            $time = current_time('mysql');
            $user = $user = get_userdata($user_id);
            $data = array(
                'comment_type' => 'fw_module_progress',
                'user_id' => $user_id,
                'comment_date' => $time,
                'comment_approved' => $module_id,
                'comment_karma' => 1,
                'comment_author' => $user->display_name,
                'comment_author_email' => $user->user_email

            );

            $comment_id = wp_insert_comment($data);

        } else {
            $comment = array();
            $comment['comment_ID'] = $user_module_status->comment_ID;
            $comment['comment_approved'] = $module_id;
            $comment['comment_karma'] = 1;
            wp_update_comment( $comment );

            $comment_id = $user_module_status->comment_ID;
        }

        return $comment_id;


    } // end fw_module_complete


    /**
     * Module progress functionality off of a given unit ID
     *
     * @return $module_progress return the module progress percent
     *
     */
    public function fw_module_progress_at_unit($unit_id) {

        $unit = get_term($unit_id, 'module');

        $module_units = get_term_children($unit->parent, 'module' );

        $completed = 0;
        $total = 0;
        foreach ($module_units as $module_unit) {
            $total++;
            if ($module_unit === $unit_id) {
                $completed = $total;
            }
        }

        $module_progress = ($completed/$total) * 100;

        return $module_progress;


    } // end fw_module_progress

    /**
     * Course progress functionality off of a given module ID
     *
     * @return $course_progress return the course progress percent at a module level
     *
     */
    public function fw_course_progress_at_module($module_id) {

        $module_number = get_term_meta($module_id, 'module_number',true );

        $course_progress = ($module_number/12) * 100;

        return $course_progress;


    } // end fw_course_progress_at_module


    /**
     * Returns next and previous lesson IDs.
     *
     * @since  1.0.0
     * @param  integer $lesson_id Lesson ID.
     * @return array Multi-dimensional array of previous and next lesson IDs.
     */
    public function fw_get_prev_next_lessons( $lesson_id = 0 ) {
        // For modules, $lesson_id is the first lesson in the module.
        $links               = array();
        $course_id           = Sensei()->lesson->get_course_id( $lesson_id );
        $course_lessons = Sensei()->course->course_lessons($course_id);

        if ( is_array( $course_lessons ) && count( $course_lessons ) > 0 ) {
            $found = false;

            foreach ( $course_lessons as $item ) {

                if ( isset( $item->ID ) && $found ) {
                    $links['next'] = $item->ID;
                    break;
                } else {
                    $links['next'] = '';
                }
                if ( isset( $item->ID ) && (absint( $item->ID ) === absint( $lesson_id )) ) {
                    $found = true;
                } else {
                    $links['previous'] = $item->ID;

                }
            }
        }

        return $links;
    } // End fw_get_prev_next_lessons()

} // end FundaWande_Lms
