<?php

namespace HTML_Forms\Actions;

use HTML_Forms\Form;
use HTML_Forms\Submission;
use PHPUnit\Runner\Exception;

class Download extends Action {

   public $type = 'download';
   public $label = 'Download file';

   public function __construct() {
       $this->label = __( 'Download file', 'html-forms' );

       add_action( 'init', array( $this, 'maybe_download_files' ) );
   }

   /**
   * @return array
   */
   private function get_default_settings() {
       $defaults = array(
          'files' => array(),
          'method' => '',
       );
       return $defaults;
   }

   /**
   * @param array $settings
   * @param string|int $index
   */ 
   public function page_settings( $settings, $index ) {
       wp_enqueue_media(); // Include scripts for media selector

       $settings = array_merge( $this->get_default_settings(), $settings );
       ?>
       <span class="hf-action-summary"><?php printf( '' ); ?></span>
       <input type="hidden" name="form[settings][actions][<?php echo $index; ?>][type]" value="<?php echo $this->type; ?>" />
       <table class="form-table">
           <tr>
               <th><label><?php echo __( 'Files', 'html-forms' ); ?></label></th>
               <td>
                   <div class="file-list"><?php
                       foreach ( $settings['files'] as $key => $file ) :
                           ?><div class="download-file">
                               <input name="form[settings][actions][<?php echo $index; ?>][files][]" value="<?php echo esc_url( $file ); ?>" type="text" class="regular-text" placeholder="" required />
                           </div><?php
                       endforeach;
                   ?></div>
                   <a href="#" class="button hf-add-files" data-choose="Choose a file" data-update="Insert file URL"><?php _e( 'Add files', 'html-forms' ); ?></a>
               </td>
           </tr>
       </table>
        <?php
   }

    /**
     * Processes this action
     *
     * @param array $settings
     * @param Submission $submission
     * @param Form $form
     */
    public function process( array $settings, Submission $submission, Form $form ) {

        $unique = bin2hex( random_bytes( 10 ) );
        set_transient( $unique, '1', HOUR_IN_SECONDS );
        $download_url = esc_url_raw( add_query_arg( array(
            'action' => 'hf-download-files',
            'hf-form' => $form->ID,
            'nonce' => wp_create_nonce( 'hf-download' ),
            'id' => $unique,
        ), home_url() ) );

        wp_send_json( array( 'redirect_url' => $download_url ) );
    }

    /**
     *
     * @throws \Exception
     */
    public function maybe_download_files() {
        if ( ! isset( $_GET['action'], $_GET['hf-form']) || $_GET['action'] !== 'hf-download-files' ) {
            return;
        }

        // Verify nonce
        if ( ! wp_verify_nonce( $_GET['nonce'], 'hf-download' ) ) {
            return;
        }

        // Ensure it can only be downloaded once
        $id = sanitize_text_field( $_GET['id'] );
        if ( ! isset( $id ) || ! get_transient( $id ) ) { // Transient should be set and positive to be able to download
            header("HTTP/1.0 403 Forbidden to download this file");
            exit;
        } else {
            delete_transient( $id ); // Remove transient to not allow any future downloads
        }

        try {
            $form = hf_get_form( $_GET['hf-form'] );
        } catch ( Exception $e ) {
            wp_die( __( 'Could not find download files.', 'html-forms' ) );
        }

        $actions = $form->settings['actions'];

        $files = array();
        foreach ( $actions as $action ) {
            if ( $action['type'] === 'download' ) {
                $files = array_merge( $files, array_map( 'esc_url_raw', $action['files'] ) );
            }
        }


        foreach ( $files as $file ) {
            $attachment_id = attachment_url_to_postid( $file );
            if ( $attachment_id ) {
                $file_path = get_attached_file( $attachment_id, false );
                $this->download_file( $file_path );
            }
        }

        exit;
    }

    /**
     * Download a specific file from a path.
     *
     * @param $path
     * @return bool
     */
    public function download_file( $path ) {
        $filename = urlencode( basename( $path ) );

        if ( ! $file = @fopen( $path, "rb" ) ) {
            trigger_error( 'Could not open file' );
            return false;
        }

        header( "Content-Type: application/octet-stream" );
        header( "Content-Disposition: attachment; filename=\"$filename\"" );

        global $is_IE;

        if ( $is_IE && is_ssl() ) { // IE bug prevents download via SSL when Cache Control and Pragma no-cache headers set.
            header( 'Expires: Wed, 11 Jan 1984 05:00:00 GMT' );
            header( 'Cache-Control: private' );
        } else {
            nocache_headers();
        }

        set_time_limit(0);
        while ( ! feof( $file ) ) {
            print( @fread( $file, 1024*8 ) );
            ob_flush();
            flush();
        }

        exit;
    }
}



add_action( 'admin_footer', '\HTML_Forms\Actions\media_selector_print_scripts' );

function media_selector_print_scripts() {

    ?><script type='text/javascript'>
        document.addEventListener('DOMContentLoaded', function() {

            // Uploading files
            var file_frame;
            var fileList = document.querySelector('.file-list');
            var index = 0;

            if (document.querySelector('.hf-add-files')) {
                document.querySelector('.hf-add-files').addEventListener('click', function( event ){
                    event.preventDefault();

                    // If the media frame already exists, reopen it.
                    if ( file_frame ) {
                        return file_frame.open(); // Open frame
                    }

                    // Create the media frame.
                    file_frame = wp.media.frames.file_frame = wp.media({
                        title: 'Select file(s)',
                        button: { text: 'Add file(s)', },
                        multiple: true,
                    });

                    // When an image is selected, run a callback.
                    file_frame.on( 'select', function() {
                        var attachments = file_frame.state().get('selection').toJSON();

                        for (var i = 0; i < attachments.length; i++) {
                            var wrap = document.createElement('div');
                                wrap.className = 'download-file';

                            var input = document.createElement('input');
                                input.name = 'form[settings][actions][' + index + '][files][]';
                                input.type = 'text';
                                input.className = 'download-file-url';
                                input.value = attachments[i].url;

                            wrap.appendChild(input);
                            fileList.appendChild(wrap);
                        }
                    });

                    file_frame.open(); // Finally, open the modal
                });
            }
        });
    </script><?php
}
