/**
 * Import the block replacer.
 */
import {BlockReplacer} from "./BlockReplacer";

/**
 * Filter the video block and replace it with YouTube block if local video is chosen.
 */
wp.hooks.addFilter('editor.BlockEdit', 'eml-switch-video-block', (BlockEdit) => {
  return (props) => {
    if (props.name === 'core/video' && props.attributes.src && props.attributes.src.startsWith('https://www.youtube.com')) {
      return <BlockReplacer clientId={props.clientId} blockType={"core/embed"} attributes={ { "providerNameSlug":"youtube","responsive":true,"url": props.attributes.src } } />
    }

    return <BlockEdit {...props}/>;
  };
});

