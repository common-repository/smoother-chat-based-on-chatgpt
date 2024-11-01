<?php

/*ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL); */

load_plugin_textdomain( 'smoother-chat', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

/*
Plugin Name:        Smoother-Chat based on ChatGPT
Plugin URI:         https://www.360-grad-camper.com/smoother-chat
Description:        A plugin to easily include a ChatGPT window via short code.
Version:            1.0
License:            GPL v2 or later
License URI:        https://www.gnu.org/licenses/gpl-2.0.html
Author:             Christian Muehlbauer
Text Domain:        smoother-chat
*/

//require_once('vendor/autoload.php'); // for guzzle
//require_once('vendor2/autoload.php'); // for Tectalic

//use Tectalic\OpenAi\Authentication;
//use Tectalic\OpenAi\Client; 
//use Tectalic\OpenAi\Manager;

// Registriere die Funktion für das Admin-Menü
add_action('admin_menu', 'scgp_chatgpt_plugin_menu');

$api_key; // define it globally
$start_prompt_php;
$tokens_sent;
$max_tokens;
$max_tokens = get_option('scgp_chatgpt_max_tokens');
if( get_option('scgp_chatgpt_api_key') == false){
    update_option('scgp_chatgpt_api_key', "0");
}
$api_key = get_option('scgp_chatgpt_api_key');
$start_prompt_php = get_option('scgp_chatgpt_start_prompt');
$current_date = date('Y-m-d');
$last_counter_reset_date = get_option('scgp_last_counter_reset_date');


// Funktion zur Erstellung des Admin-Menüs und der Unterseiten
function scgp_chatgpt_plugin_menu() {
    // Hinzufügen des Hauptmenüs
    add_menu_page(
        'Smoother Chat', // Seitentitel
        'Smoother Chat', // Menütitel
        'manage_options', // Benutzerrolle
        'smoother-chat', // Slug
        'scgp_chatgpt_plugin_settings_page', // Callback-Funktion für die Inhalte der Seite
        'dashicons-admin-generic', // Icon
        100 // Position im Menü
    );
}
function my_reset_tokens_function() {
    if (isset($_POST['chatgpt_reset_tokens']) && isset($_POST['chatgpt_reset_tokens_nonce'])) {
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['chatgpt_reset_tokens_nonce'])), 'chatgpt_reset_tokens_action')) {
            wp_die(esc_html__('Security Check failed.', 'smoother-chat'));
        }
        scgp_reset_tokens_function();
    }
}

function scgp_reset_tokens_function() {

    global $current_date;
    update_option('scgp_chatgpt_tokens_sent', 0);
    update_option('scgp_chatgpt_tokens_received', 0);
    update_option('scgp_last_counter_reset_date', $current_date);
}

// Callback-Funktion für die Inhalte der Plugin-Seiten
function scgp_chatgpt_plugin_settings_page() {

    global $api_key;
    global $start_prompt_php; 
    global $last_counter_reset_date; 


    // Überprüfe die Berechtigung
    if (!current_user_can('manage_options')) {
        wp_die(esc_html_e( 'You do not have permission to access this page.', 'smoother-chat' ));
    }

    // Speichern der Einstellungen, wenn das Formular abgeschickt wurde
    if (isset($_POST['chatgpt_save_settings'])) {
        // Überprüfe die Nonce-Sicherheit
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['chatgpt_settings_nonce'])), 'chatgpt_settings')) {
            wp_die(esc_html_e( 'Security Check failed.', 'smoother-chat' ));
        }

        // Speichere die Einstellungen
        if(sanitize_text_field($_POST['chatgpt_api_key'])){

            $api_key = sanitize_text_field($_POST['chatgpt_api_key']);
            //$api_key = $_POST['chatgpt_api_key'];

            if (!empty($api_key)) {
                // Speichere die Einstellungen
                update_option('scgp_chatgpt_api_key', $api_key);
        
                // Zeige eine Erfolgsmeldung an
                echo '<div class="notice notice-success is-dismissible"><p>';
                echo esc_html_e( 'Settings saved successfully.', 'smoother-chat' );
                echo '</p></div>';
            } 
            
        } 
        

        update_option('scgp_chatgpt_start_prompt', sanitize_textarea_field($_POST['chatgpt_start_prompt']));

        

        $max_tokens = absint($_POST['chatgpt_max_tokens']); // Wandele den eingegebenen Wert in eine positive Ganzzahl um
        update_option('scgp_chatgpt_max_tokens', $max_tokens); // Speichere den Wert in der Datenbank

    } 

    // Hole die gespeicherten Einstellungen
    $api_key = get_option('scgp_chatgpt_api_key');


    $visibleLength = 6; // Anzahl der sichtbaren Zeichen (z.B. die ersten drei Zeichen)
    if (strlen($api_key) > $visibleLength) {
        $maskedLength = strlen($api_key) - $visibleLength; // Anzahl der zu maskierenden Zeichen
    }else {
        $maskedApiKey = $api_key;
    }
   
    $maskedApiKey = substr($api_key, 0, $visibleLength) . str_repeat('*', $maskedLength); // Erstellt den maskierten API-Schlüssel

    // HTML-Code für die Einstellungsseite 

    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Smoother-Chat Plugin Settings', 'smoother-chat' ); ?></h1>

        <form method="post" action="">
            <?php wp_nonce_field('chatgpt_settings', 'chatgpt_settings_nonce'); 
            
            $max_tokens = get_option('scgp_chatgpt_max_tokens'); // Lade den aktuellen Wert aus der Datenbank
            $tokens_sent = get_option('scgp_chatgpt_tokens_sent');
            $tokens_received = get_option('scgp_chatgpt_tokens_received');
            ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="chatgpt_api_key"><?php esc_html_e( 'OpenAI API-Key:', 'smoother-chat' ); ?></label></th>
                    <td><input type="text" name="chatgpt_api_key" id="chatgpt_api_key" class="regular-text"
                               value="" />
                               <p class="description">
                               <?php if($maskedApiKey){
                                   esc_html_e( 'The currently stored OpenAI key is :', 'smoother-chat' );
                                   //echo $maskedApiKey;
                                   echo esc_html($maskedApiKey);
                               } ?>
                               </p>
                               <br>
                               <p class="description"><?php esc_html_e( 'You can request an ChatGPT API Key on the website of ', 'smoother-chat' ); ?>
                               <a href="https://platform.openai.com/account/api-keys/" ><?php esc_html_e( 'OpenAI. ', 'smoother-chat' ); ?></a><?php esc_html_e( 'Make sure that you set any necessary limits on OpenAI regarding the maximum requests, e.g. per month.', 'smoother-chat' ); ?>
                               

                               </p>
                               </td> 
                </tr>
                <tr> 
                    <th scope="row"><label for="chatgpt_start_prompt"><?php esc_html_e( 'Start-Prompt:', 'smoother-chat' ); ?></label></th>
                    <td>
                        <textarea name="chatgpt_start_prompt" id="chatgpt_start_prompt" rows="5"
                                  cols="50"><?php echo esc_textarea(stripslashes($start_prompt_php)); ?></textarea>
                        <p class="description"><?php esc_html_e( 'Enter the text for the start prompt here. Describe how you want your chat assistant to behave. For example: "You are a helpful, knowledge sharing chatbot who is expert in Do-it-Yourself camper van teaching."', 'smoother-chat' ); ?></p>
                    </td>
                </tr>


                <tr> 
                    <th scope="row"><label for="chatgpt_max_tokens"><?php esc_html_e( 'Maximum Tokens per Day: ', 'smoother-chat' ); ?></label></th>
                    <td>
                        <textarea name="chatgpt_max_tokens" id="chatgpt_max_tokens" rows="1"
                                  cols="50"><?php echo esc_textarea(stripslashes($max_tokens)); ?></textarea>
                        <p class="description"><?php esc_html_e( 'Enter here the maximum number of tokens you want allow per Day.', 'smoother-chat' ); ?></p>

                    </td>
                </tr>
            </table>
            <table class="form-table">
            <tr> 
            <th scope="row"><label for="chatgpt_api_key"><?php esc_html_e( 'Statistics:', 'smoother-chat' ); ?></label></th>

                
                <th><?php esc_html_e( 'Input-Prompt Tokens', 'smoother-chat' ); ?></th>
                <th><?php esc_html_e( 'Output-Response Tokens', 'smoother-chat' ); ?></th>
                <th><?php esc_html_e( 'Total Tokens', 'smoother-chat' ); ?></th>
            </tr>
            <tr>

            <td>
<?php
                printf( 
                    /* translators: %s: Name of a city */ 
                    esc_html__( 'Last active day (%s)', 'smoother-chat' ), esc_html($last_counter_reset_date));
?>
            </td>

                <td><?php echo esc_html($tokens_sent); ?></td>

                <td><?php echo esc_html($tokens_received); ?></td>
                <?php  $tokens_total_calculated = ($tokens_sent + $tokens_received); ?>
                <td><?php echo esc_html($tokens_total_calculated); ?></td>

            </tr>
        </table>
        <p>
        </p>
        <td>
        <input type="hidden" name="chatgpt_reset_tokens_nonce" value="<?php echo wp_create_nonce('chatgpt_reset_tokens_action'); ?>">
        <input type="submit" name="chatgpt_reset_tokens" class="button-primary" value="<?php esc_html_e( 'Reset Tokens', 'smoother-chat' ); ?>" />
        <p class="description"><?php esc_html_e( 'Last reset date: ', 'smoother-chat' ); 
        echo esc_html($last_counter_reset_date);
        ?> 
        </td>
            <p class="submit">
                <input type="submit" name="chatgpt_save_settings" class="button-primary" value="<?php esc_html_e( 'Save settings.', 'smoother-chat' ); ?>" />
            </p>
        </form>
    </div>
    <?php
}

// Shortcode-Handler für das Chat-GPT-Fenster
function scgp_chatgpt_shortcode_handler($atts) {
    //$start_prompt = 'you are a helpful, knowledge sharing chatbot who is always adding Thanks at the end of a response.';
    global $start_prompt_php;
    ob_start();

    // HTML-Code für das Chat-GPT-Fenster
    ?>

    <div id="chatgpt-container">
    <div id="chatgpt-minimize" title="<?php esc_html_e( 'Minimize.', 'smoother-chat' ) ?>"></div>
        <div id="chatgpt-messages"></div>
        <input type="text" id="chatgpt-input" placeholder="<?php esc_html_e( 'Ask me something...', 'smoother-chat' ) ?>" />
        <button id="chatgpt-submit" class="hidden"><?php esc_html_e( 'Send', 'smoother-chat' ) ?></button>
        <button id="chatgpt-reset"><?php esc_html_e( 'New conversation', 'smoother-chat' ) ?></button>
        <button id="chatgpt-submit-small"><?php esc_html_e( 'Send', 'smoother-chat' ) ?></button>
        
    </div>


    <script>

jQuery(document).ready(function($) {
    var ajaxurl = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';


        $('#chatgpt-submit').click(function() { 

            const inputField = document.getElementById("chatgpt-input");
            const question = inputField.value.trim();
            const prompt = generatePrompt(question);
            //console.log("PROMPT: ", prompt); 

            if (question !== "") {
                addChatMessage(question, "...");
                var data = {
                    action: 'scgp_my_ajax_plugin_hello',
                    security: '<?php echo wp_create_nonce('scgp_ajax_nonce'); ?>',
                    prompt_nonce: '<?php echo wp_create_nonce('prompt_action'); ?>', // Add this line
                    prompt: prompt,
                };

            $.post(ajaxurl, data, function(response) {
                $('#response-container').html(response);

                addChatMessageResponseOnly(/*question, */response,"..."); 
                    conversation.push({ 
                        question: question,
                        answer: response
                    });
            });

                inputField.value = "";
            }
        });

      });

        var conversation = [];

        // Prompt generieren
        function generatePrompt(question) {
  
            let prompt = <?php echo wp_json_encode($start_prompt_php); ?>;

            //console.log("Das ist der Start-Prompt:", prompt);

            for (let i = 0; i < conversation.length; i++) {
                prompt += ` I say: ${conversation[i].question}. You reply: ${conversation[i].answer}.`;
            }

            prompt += ` I say: ${question}. You reply:`;

            return prompt;
        }

        // Chat-Nachrichten hinzufügen
        function addChatMessage(question, answer) {
            const chatMessages = document.getElementById("chatgpt-messages");

            const questionDiv = document.createElement("div");
            questionDiv.classList.add("chatgpt-message");
            questionDiv.classList.add("question");
            //questionDiv.textContent = "<b><?php esc_html_e( 'Me: ', 'smoother-chat' ) ?></b>" + question;
            questionDiv.innerHTML = "<b><?php esc_html_e( 'Me: ', 'smoother-chat' ) ?></b>" + question;

            const answerDiv = document.createElement("div");
            answerDiv.classList.add("chatgpt-message");
            answerDiv.classList.add("answer");
            answerDiv.textContent = answer;

            chatMessages.appendChild(questionDiv);
            chatMessages.appendChild(answerDiv);


            // Bildlauf zum Ende des Chatfensters
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

         // Chat-Nachrichten hinzufügen
         function addChatMessageResponseOnly(answer) { 
            const chatMessages = document.getElementById("chatgpt-messages");

            const answerDiv = document.createElement("div");
            const breakDiv = document.createElement("div");
            answerDiv.classList.add("chatgpt-message");
            answerDiv.classList.add("answer");
            //answerDiv.textContent = "<?php esc_html_e( '360-Camper-KI: ', 'smoother-chat' ) ?>" + answer;
            answerDiv.innerHTML = "<b><?php esc_html_e( '360-Camper-KI: ', 'smoother-chat' ) ?></b>" + answer;

            breakDiv.classList.add("chatgpt-message");
            breakDiv.classList.add("answer");
            breakDiv.textContent = "...";
            //console.log("answerDiv",answerDiv); 

            chatMessages.appendChild(answerDiv);
            chatMessages.appendChild(breakDiv);

            // Bildlauf zum Ende des Chatfensters
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }


        // Event-Handler für das Absenden der Frage (minimiert)
        document.getElementById("chatgpt-submit-small").addEventListener("click", function () {
            const inputField = document.getElementById("chatgpt-input");
            const question = inputField.value.trim();

            if (question !== "") { 
                addChatMessage(question, "...");
                //tbdcall vorher: callOpenAIChatAPI(question);
                inputField.value = "";
            }
        }); 

        // Event-Handler für den "Neue Unterhaltung starten"-Button
        document.getElementById("chatgpt-reset").addEventListener("click", function () {
            conversation = [];
            document.getElementById("chatgpt-messages").innerHTML = "";
        });

        // ...
var isMinimized = false;

// ...
document.addEventListener("DOMContentLoaded", function() {
var chatContainer = document.getElementById("chatgpt-container"); 
            chatContainer.classList.add("minimized");
            isMinimized = true;
        document.getElementById("chatgpt-submit").classList.add("hidden");
        document.getElementById("chatgpt-submit-small").classList.remove("hidden");
        });

// Event-Handler für das Minimieren des Chat-Fensters
document.getElementById("chatgpt-minimize").addEventListener("click", function () {
    var chatContainer = document.getElementById("chatgpt-container");

    if (isMinimized) {
        chatContainer.classList.remove("minimized");
        isMinimized = false;
        document.getElementById("chatgpt-submit").classList.remove("hidden");
        document.getElementById("chatgpt-submit-small").classList.add("hidden");
    } else {
        chatContainer.classList.add("minimized");
        isMinimized = true;
        document.getElementById("chatgpt-submit").classList.add("hidden");
        document.getElementById("chatgpt-submit-small").classList.remove("hidden");
    }
});

// Event-Handler für das Minimieren des Chat-Fensters
document.getElementById("chatgpt-input").addEventListener("click", function () {
    var chatContainer = document.getElementById("chatgpt-container");
    if (isMinimized) {
        chatContainer.classList.remove("minimized");
        isMinimized = false;
        document.getElementById("chatgpt-submit").classList.remove("hidden");
        document.getElementById("chatgpt-submit-small").classList.add("hidden");
    } 
});

    </script>
    <?php

    return ob_get_clean();
}

add_shortcode("smoother-chat", "scgp_chatgpt_shortcode_handler");

// Stil für das Chat-Fenster
function scgp_chatgpt_enqueue_styles() {
    wp_enqueue_style('chatgpt-styles', plugin_dir_url(__FILE__) . 'css/smoother-chat-styles.css');
}

// AJAX-Funktion zum Aufrufen der "hello" Funktion
function scgp_my_ajax_plugin_hello() {
    global $api_key;
    global $max_tokens;

    global $current_date;
    global $last_counter_reset_date;

    if ($current_date != $last_counter_reset_date) {
        update_option('scgp_chatgpt_tokens_sent', 0);
        update_option('scgp_chatgpt_tokens_received', 0);
        update_option('scgp_last_counter_reset_date', $current_date);
    }

    //$promptParameter = $_POST['prompt'];
    //$promptParameter = isset( $_POST['prompt'] ) ? sanitize_text_field( wp_unslash( $_POST['prompt'] ) ) : '';

    $promptParameter = '';

if (function_exists('wp_verify_nonce') && isset($_POST['prompt']) && isset($_POST['prompt_nonce'])) {
    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['prompt_nonce'])), 'prompt_action')) {
        wp_die(esc_html__('Security Check failed.', 'smoother-chat'));
    }
    $promptParameter = sanitize_text_field(wp_unslash($_POST['prompt']));
}


    $prompt_tokens_offset = get_option('scgp_chatgpt_tokens_sent');
    $completion_tokens_offset = get_option('scgp_chatgpt_tokens_received');

    if (($prompt_tokens_offset + $completion_tokens_offset) < $max_tokens) {
        $url = 'https://api.openai.com/v1/chat/completions';

        $request_params = array(
            'model' => 'gpt-3.5-turbo',
            'max_tokens' => 256,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $promptParameter
                ),
            ),
            'functions' => array(
                array(
                    'name' => 'get_current_weather',
                    'description' => 'Get the current weather in a given location',
                    'parameters' => array(
                        'type' => 'object',
                        'properties' => array(
                            'location' => array(
                                'type' => 'string',
                                'description' => 'The worldwide city and state, e.g. San Francisco, CA',
                            ),
                            'format' => array(
                                'type' => 'string',
                                'description' => 'The temperature unit to use. Infer this from the users location.',
                                'enum' => array('celsius', 'farhenheit'),
                            ),
                            'num_days' => array(
                                'type' => 'integer',
                                'description' => 'The number of days to forecast',
                            ),
                        ),
                        'required' => array('location', 'format', 'num_days'),
                    ),
                ),
                array(
                    'name' => 'delete_page_or_pages_from_PDFZorro',
                    'description' => 'Delete one or more pages from a PDF file on PDFZorro',
                    'parameters' => array(
                        'type' => 'object',
                        'properties' => array(
                            'pages' => array(
                                'type' => 'integer',
                                'description' => 'Contains page number which has to be deleted',
                            ),
                        ),
                        'required' => array('pages'),
                    ),
                ),
            ),
            'function_call' => 'auto',
        );

        $response = wp_remote_post(
            $url,
            array(
                'body' => wp_json_encode($request_params),
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $api_key,
                ),
            )
        );

        if (is_wp_error($response)) {
            echo esc_html__('Error making API request.', 'smoother-chat');
        } else {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body);

            $params = json_decode($data->choices[0]->message->function_call->arguments, true);
            $params2 = $data->choices[0]->message->function_call->name;

            if ($params != 0) {

                echo "Call function with name: " . esc_html($params2);

            }

            echo esc_html($data->choices[0]->message->content);
        }

        $usage  = $data->usage;
        $prompt_tokens += $usage->prompt_tokens;
        $completion_tokens = $usage->completion_tokens;
        $total_tokens = $usage->total_tokens;

        update_option('scgp_chatgpt_tokens_sent', $prompt_tokens + $prompt_tokens_offset);
        update_option('scgp_chatgpt_tokens_received', $completion_tokens + $completion_tokens_offset);

    } else {
        echo esc_html__('I can currently only do a certain number of free conversations per day. Try again tomorrow!', 'smoother-chat');
    }

    wp_die();
}  

add_action('wp_ajax_scgp_my_ajax_plugin_hello', 'scgp_my_ajax_plugin_hello');
add_action('wp_ajax_nopriv_scgp_my_ajax_plugin_hello', 'scgp_my_ajax_plugin_hello');

add_action('wp_enqueue_scripts', 'scgp_chatgpt_enqueue_styles');

add_action('init', 'my_reset_tokens_function');



register_uninstall_hook(__FILE__, 'scgp_chatgpt_plugin_uninstall');

function scgp_chatgpt_plugin_uninstall() {
    delete_option('scgp_chatgpt_api_key');
    delete_option('scgp_chatgpt_start_prompt');
    delete_option('scgp_chatgpt_tokens_sent');
    delete_option('scgp_chatgpt_tokens_received');
    delete_option('scgp_last_counter_reset_date');
    delete_option('scgp_chatgpt_max_tokens');


}


