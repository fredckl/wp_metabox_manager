<?php
/**
 * Created by PhpStorm.
 * User: frederickoller
 * Date: 2018-12-12
 * Time: 07:11
 */

class FkMetaboxManager
{
    /**
     * @var string Indetifiant de la boxe
     */
    private $id;

    /**
     * @var string Titre de la boxe
     */
    private $title;

    /**
     * @var strign Applicable pour un type de poste uniquement
     */
    private $post_type;

    /**
     * @var array Champs de meta post
     */
    private $_fields;

    /**
     * @var array Type de champs autorisé
     */
    static private $_postTypes = [];

    /**
     * @var array Fichier JS à charger
     */
    static private $_jsFiles = [];

    /**
     * @var array Fichier CSS à charger
     */
    static private $_cssFiles = [];

    /**
     * @var string Classe englobant les inputs
     */
    private $_clsWrapInput;

    static public function addPostType($id, $name, $singular_name, $public = true, $has_archive = true)
    {

        static::$_postTypes[] = compact('id', 'name', 'singular_name', 'public', 'has_archive');
    }

    static public function addCss($handle, $src, $deps = array(), $ver = false, $media = 'all')
    {
        static::$_cssFiles[] = compact('handle', 'src', 'deps', 'ver', 'media');
    }

    static public function addJS($handle, $src, $deps = array(), $ver = false, $in_footer = false)
    {
        static::$_jsFiles[] = compact('handle', 'src', 'deps', 'ver', 'in_footer');
    }

    public function __construct ($id, $title, $post_type, $classWrapInput = 'fk-metabox')
    {
        add_action( 'admin_init', [$this, 'registerPostType']);
        add_action('admin_init', [$this, 'create_meta_box']);
        add_action('admin_enqueue_scripts', [$this, 'loadAssets']);
        add_action('save_post', [$this, 'save']);
        $this->id = $id;
        $this->title = $title;
        $this->post_type = $post_type;
        $this->_clsWrapInput = $classWrapInput;
    }

    public function registerPostType ()
    {
        foreach (static::$_postTypes as $postType) {

            \register_post_type( $postType['id'],
                array(
                    'labels' => array(
                        'name' => $postType['name'],
                        'singular_name' => $postType['singular_name']
                    ),
                    'public' => $postType['public'],
                    'has_archive' => $postType['has_archive'],
                )
            );
        }
    }

    /**
     * Chargement des assets
     */
    public function loadAssets()
    {
        foreach (static::$_jsFiles as $jsFile) {
            \wp_register_script(
                $jsFile['handle'],
                $jsFile['src'],
                $jsFile['deps'],
                $jsFile['ver'],
                $jsFile['in_footer']);
            \wp_enqueue_script($jsFile['handle']);
        }

        foreach (static::$_cssFiles as $cssFile) {
            \wp_register_style(
                $cssFile['handle'],
                $cssFile['src'],
                $cssFile['deps'],
                $cssFile['ver'],
                $cssFile['media']);
            \wp_enqueue_style($cssFile['handle']);
        }
    }

    public function add ($id, $label, $input_type = 'text', $default = '', $cls = null)
    {
        $this->_fields[] = [
            'id' => $this->id . '_' . $id,
            'cls' => $cls ? $cls : sprintf('%s %s-%s', $this->_clsWrapInput, $this->_clsWrapInput, $id),
            'label' => $label,
            'type' => $input_type,
            'default' => $default
        ];

        return $this;
    }


    public function create_meta_box ()
    {
        if (function_exists('add_meta_box')) {
            add_meta_box($this->id, $this->title, [$this, 'render'],  $this->post_type);
        }
    }

    public function render ()
    {
        global $post;

        foreach ($this->_fields as $field) {
            $value = get_post_meta($post->ID, $field['id'], true);

            if ($value == '') {
                $value = $field['default'];
            }

            extract($field);
            require __DIR__ . DS . $this->_createPaths(['views', 'admin', 'input' . '.php']);
        }

        if (!empty($this->_fields)) {
            echo '<input type="hidden" name="' . $this->id . '_nonce" value="'. wp_create_nonce($this->id) .'" />';
        }
    }

    private function _createPaths(array $paths)
    {
        return implode(DS, $paths);
    }


    /**
     * @param $post_id
     * @param $post
     * @param $update
     * @return bool
     */
    public function save ($post_id, $post, $update)
    {
        // Vérification de la capacité de pouvoir modifier un article
        if (!\current_user_can('edit_post', $post_id)) {
            return $post_id;
        }
        // Vérification du jeton de sécurité
        if (!\wp_verify_nonce($_POST[$this->id . '_nonce'], $this->id)) {
            return $post_id;
        }

        foreach ($this->_fields as $field) {
            $id = $field['id'];
            if (isset($_POST[$id])) {
                if (empty($_POST[$id])) {
                    \delete_post_meta($post_id, $id);
                } else {
                    if (\get_post_meta($post_id, $id)) {
                        \update_post_meta($post_id, $id, $_POST[$id]);
                    } else {
                        \add_post_meta($post_id, $id, $_POST[$id]);
                    }
                }
            }
        }
    }
}