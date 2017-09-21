<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Content.bfloadarticle
 *
 * @copyright Copyright (C) 2017 Jonathan Brain. All rights reserved.
 * @license   GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
*/

// No direct access
defined('_JEXEC') or die;

jimport('joomla.plugin.plugin');

class plgContentBFLoadArticle extends JPlugin{
  private static $_processing = false;
  
  function __construct(&$subject, $config){
    parent::__construct($subject, $config);
  }

  /**
    */
  public function onAfterRender(){
   
    if (self::$_processing) {
      return;
    }

    $this->_app = JFactory::getApplication('site');
     if($this->_app->isAdmin()) return;
    
    $buffer = JResponse::getBody();
    if (strpos($buffer, '{bfloadarticle ') === false) {
      return;
    }

    $processed = false;
    $matches = array();
    if (preg_match_all('/{(bfloadarticle)\\s+([^}]+)}/i', $buffer, $matches, PREG_SET_ORDER)) {  
      foreach ($matches as $match) {       
        $html = '';
        $arguments = array();
        $paramsarray = explode('|',$match[2]);      

        if (count($paramsarray) && !empty($paramsarray[0])) {
          switch (strtolower($paramsarray[0])) {
            default:
              $html = $this->_loadarticle($paramsarray[0], @$paramsarray[1]);
              break;
          }
        }

        $buffer = str_replace($match[0], $html, $buffer);
        $processed = true;
      }     
    }
    
    if ($processed) {
      JResponse::setBody($buffer);
    }
  }
  
  /**
    */
  private function _loadarticle($id, $options=null){
    $db = JFactory::getDBO();
    $query = 'SELECT id FROM #__content WHERE id = ' . $db->quote($id);
    $db->setQuery($query);
    $id = $db->loadResult();
    if (empty($id)) {
	  return null;
	}

    $html = null;    
    if (!empty($id)) try {
      require_once(JPATH_SITE . '/components/com_content/models/article.php');
      $articleModel = new ContentModelArticle();
      $articleModel->getState();
      $articleModel->setState('filter.published', '');
      $articleModel->setState('filter.archived', '');
      $article = $articleModel->getItem($id);
      if (!empty($article)) {
        $html = $this->_displayarticle($article);
      }
    }
    catch (Exception $e) {
    }
    
    if (empty($html)) {
      if ($this->params->get('show_notfound')) {
        $html = JText::_('COM_CONTENT_ERROR_ARTICLE_NOT_FOUND') . ' : ' . $alias;
      }
      else {
        return null;
      }
    }

    return $html;
    return '<div class="bfloadarticle-' . $article->id . ' bfloadarticle-' . $article->alias . '">' . $html . '</div>';
  }

  /**
    */
  private function _displayarticle($item, $options=null) {
    $published = true;
    $hdr = '';
    $wrn = '';

    if ($options & 1) :
      $hdr .= '<h2 itemprop="name">' . htmlspecialchars($item->title) . '</h2>';
    endif;

    if ($item->state == 0) :
      $wrn .= '<span class="label label-warning">' . JText::_('JUNPUBLISHED') . '</span>';
      $published = false;
    endif;
    if (strtotime($item->publish_up) > strtotime(JFactory::getDate())) :
      $wrn .= '<span class="label label-warning">' . JText::_('JNOTPUBLISHEDYET') . '</span>';
      $published = false;
    endif;
    if ((strtotime($item->publish_down) < strtotime(JFactory::getDate())) &&
	     $item->publish_down != JFactory::getDbo()->getNullDate()) :
      $wrn .= '<span class="label label-warning">' . JText::_('JEXPIRED') . '</span>';
      $published = false;
    endif;

    if (!$published) {
      if ($this->params->get('show_unpublished')) {
        $hdr .= $wrn;
      }
      else {
        return null;
      }
    }

    if (!empty($hdr)) {
      $item->text = '<div class="page-header">' . $hdr . '</div>';
    }
    else {
      $item->text = '';
    }

    if ($published) {
      if (empty($item->fulltext)) {
        $item->text .= $item->introtext;
      }
      else {
        $item->text .= $item->fulltext;
      }

      $dispatcher = JEventDispatcher::getInstance();
      JPluginHelper::importPlugin('content');
      self::$_processing = true;
      $dispatcher->trigger('onContentPrepare', array ('plg_bfloadarticle', &$item, &$item->params, 0));
      self::$_processing = false;
    }

    return $item->text;
  }
}
?>