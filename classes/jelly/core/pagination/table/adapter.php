<?php

defined('SYSPATH') or die('No direct script access.');

abstract class Jelly_Core_Pagination_Table_Adapter
{

    /**
     * Config
     * @var array
     */
    protected $_config = array(
        'current_sort' => array('source' => 'query_string', 'key' => 'sort'),
        'current_order' => array('source' => 'query_string', 'key' => 'how'),
        'current_limit' => array('source' => 'query_string', 'key' => 'limit'),
    );

    /**
     * Builder or Model
     * @var from const
     */
    protected $_source;

    /**
     * Adapt source
     * @var Paginaton 
     */
    protected $_target;

    /**
     * Item pre page
     * @var int
     */
    protected $_limit;

    /**
     * Current column
     * @var string
     */
    protected $_sort_column;

    /**
     * Current sort direct
     * @var type 
     */
    protected $_direct;

    
    /**
     * Table action list
     * @var array
     */
    protected $_action = array();


    /**
     * Setup adapter
     * 
     * @param Jelly_Builder $source
     * @param array $config pagination config
     */
    public function __construct(Jelly_Builder $source, array $config)
    {
        $this->_target = new Pagination(array(
            'total_items' => $source->select()->count(),
            'items_per_page' => isset($config['items_per_page']) ? $config['items_per_page'] : 10,
        ));

        $this->_source = $source;

        //merge config
        $this->_config += $this->_target->config_group();



        $order_how = Request::current()->query('how') ? Request::current()->query('how') : 'DESC';
        $this->_direct = ($order_how == 'DESC') ? 'ASC' : 'DESC';


        $this->_sort_column = Request::current()->query('sort');



        if (!is_null($this->_sort_column))
            $this->_source->order_by($this->_sort_column, $order_how);
    }

    /**
     * Table sort order uri
     */
    public function sort(Jelly_Field $column, $foreign = null)
    {
        // Get the current route name
        $current_route = Route::name(Request::initial()->route());

        //Current uri
        $uri = Route::get($current_route)->uri($this->_target->route_params());

        //Current page
        $page = $this->_target->request()->query($this->_config['current_page']['key']);

        //Request params
        $params = $this->_target->request()->query();

        //Set sort params
        $params[$this->_config['current_sort']['key']] = $column->name;

        if (!is_null($foreign) && $column instanceof Jelly_Field_Supports_With)
        {
            $model = Jelly::factory($column->foreign['model'])->meta();

            if ($model->field($foreign) !== NULL)
            {
                //Set sort params
                $params[$this->_config['current_sort']['key']] = ':' . $column->foreign['model'] . '.' . $foreign;
            }
        }

        //Set sort params
        $params[$this->_config['current_order']['key']] = $this->_direct;

        $class = '';
        $arrow = '<i class="fa fa-sort pull-right"></i>';

        if ($this->_sort_column == $column->name)
        {
            $arrow = ($this->_direct == 'DESC') ? '<i class="fa fa-sort-desc pull-right"></i>' : '<i class="fa fa-sort-asc pull-right"></i>';
            $class = 'text-success';
        }

        return HTML::anchor($uri . URL::query($params), $column->label . $arrow, array('class' => $class));
    }

    /**
     * Get items collection
     * @return Jelly_Collection
     */
    public function items()
    {
        return $this->_source->offset($this->_target->offset)->limit(10)->select();
    }

    
    /**
     * Render pagination nav
     * @param string $view view path
     * @return string
     */
    public function render($view)
    {
        return $this->_target->render($view);
    }

}
