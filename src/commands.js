/**
 * Embed requirements.
 */
import { store as commandsStore } from '@wordpress/commands';
import { dispatch } from '@wordpress/data';
import { useSelect } from '@wordpress/data';
import { useMemo, useEffect } from "@wordpress/element";
import { addQueryArgs } from '@wordpress/url';
import { archive, cog, connection, external, page } from '@wordpress/icons';

/**
 * Open the URL of a record.
 *
 * Uses the URL given by the server. Records of older versions without URL use the given fallback arguments.
 *
 * @param record The record.
 * @param args The arguments for the fallback URL.
 */
function efml_open_record( record, args ) {
  // open documentations in a new window.
  if ( record.url && record.external ) {
    window.open( record.url, '_blank', 'noopener' );
    return;
  }

  // open the URL given by the server.
  if ( record.url ) {
    document.location = record.url;
    return;
  }

  // use the fallback.
  document.location = addQueryArgs( "upload.php", args );
}

/**
 * Return the search label of a record.
 *
 * The records are already filtered by the server (incl. other terms of a service), so the search is part of the
 * search label to show them in any case. The label keeps the search label of each command unique, as the
 * command palette uses it to identify the active command.
 *
 * @param record The record.
 * @param search The search.
 * @returns {string}
 */
function efml_get_search_label( record, search ) {
  return record.label + ' ' + search;
}

/**
 * Load the records of an endpoint for the given search.
 *
 * @param name The name of the endpoint.
 * @param search The search.
 * @returns {*[]}
 */
function useEfmlRecords( name, search ) {
  useEffect(() => {
    dispatch('core').addEntities([
      {
        name: name,
        kind: 'external-files-in-media-library/v1',
        baseURL: '/external-files-in-media-library/v1/' + name
      }
    ]);
  }, []);
  const query = {
    search: !!search ? search : undefined,
    per_page: 10
  };
  return useSelect((select) => {
      // bail without search, the endpoints return nothing then.
      if ( ! search ) {
        return [];
      }
      return select('core').getEntityRecords('external-files-in-media-library/v1', name, query ) || [];
    }, [ search ]
  );
}

/**
 * Initiate the custom command to search for services.
 */
dispatch( commandsStore ).registerCommandLoader( {
  name: 'external-files-in-media-library/services',
  hook: useExternalServicesInCommandPalette,
} );

/**
 * Define our custom command to load services in the command palette.
 *
 * @param search
 * @returns {{commands: *}}
 */
function useExternalServicesInCommandPalette( { search } ) {
  let records = useEfmlRecords( 'services', search );

  /**
   * Create the commands.
   */
  const commands = useMemo(() => {
    return (records ?? []).slice(0, 10).map((record) => {
      return {
        name: 'external-files-in-media-library/service-' + record.value,
        label: record.label,
        searchLabel: efml_get_search_label( record, search ),
        icon: connection,
        category: 'view',
        callback: ({ close }) => {
          efml_open_record( record, {
            page: 'efml_local_directories',
            method: record.value
          } );
          close();
        },
      };
    });
  }, [records, search]);

  return {
    commands
  };
}

/**
 * Initiate the custom command to search for external sources.
 */
dispatch( commandsStore ).registerCommandLoader( {
  name: 'external-files-in-media-library/sources',
  hook: useExternalSourcesInCommandPalette,
} );

/**
 * Define our custom command to load external sources in the command palette.
 *
 * @param search
 * @returns {{commands: *}}
 */
function useExternalSourcesInCommandPalette( { search } ) {
  let records = useEfmlRecords( 'sources', search );

  /**
   * Create the commands.
   */
  const commands = useMemo(() => {
    return (records ?? []).slice(0, 10).map((record) => {
      return {
        name: 'external-files-in-media-library/source-' + record.value,
        label: record.label,
        searchLabel: efml_get_search_label( record, search ),
        icon: archive,
        category: 'view',
        callback: ({ close }) => {
          efml_open_record( record, {
            page: 'efml_local_directories',
            method: record.method,
            term: record.value
          } );
          close();
        },
      };
    });
  }, [records, search]);

  return {
    commands
  };
}

/**
 * Initiate the custom command to search for settings, pages and documentations.
 */
dispatch( commandsStore ).registerCommandLoader( {
  name: 'external-files-in-media-library/pages',
  hook: useExternalFilesPagesInCommandPalette,
} );

/**
 * Define our custom command to load settings, pages and documentations in the command palette.
 *
 * @param search
 * @returns {{commands: *}}
 */
function useExternalFilesPagesInCommandPalette( { search } ) {
  let records = useEfmlRecords( 'pages', search );

  /**
   * Create the commands.
   */
  const commands = useMemo(() => {
    return (records ?? []).slice(0, 10).map((record) => {
      // choose the icon.
      let icon = page;
      if ( record.external ) {
        icon = external;
      }
      else if ( String( record.value ).startsWith( 'settings-' ) ) {
        icon = cog;
      }

      return {
        name: 'external-files-in-media-library/page-' + record.value,
        label: record.label,
        searchLabel: efml_get_search_label( record, search ),
        icon: icon,
        category: 'view',
        callback: ({ close }) => {
          efml_open_record( record, {
            page: 'efml_local_directories'
          } );
          close();
        },
      };
    });
  }, [records, search]);

  return {
    commands
  };
}
