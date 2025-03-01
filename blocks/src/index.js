import {BlockReplacer} from "./BlockReplacer";

wp.hooks.addFilter('editor.BlockEdit', 'eml-switch-video-block', (BlockEdit) => {
  return (props) => {
    if (props.name === 'core/video' && props.attributes.src.startsWith('https://www.youtube.com')) {
      console.log(props);
      return <BlockReplacer clientId={props.clientId} blockType={"core/embed"} attributes={ { "providerNameSlug":"youtube","responsive":true,"url": props.attributes.src } } />
    }

    return <BlockEdit {...props}/>;
  };
});

