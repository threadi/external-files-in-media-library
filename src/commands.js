/**
 * Embed requirements.
 */
import { store as commandsStore } from '@wordpress/commands';
import { dispatch } from '@wordpress/data';
import { useSelect } from '@wordpress/data';
import { useMemo, useEffect } from "@wordpress/element";
import { addQueryArgs } from '@wordpress/url';
import { connection } from '@wordpress/icons';

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
 * @returns {{commands: *, isLoading: *}}
 */
function useExternalServicesInCommandPalette( { search } ) {
  useEffect(() => {
    dispatch('core').addEntities([
      {
        name: 'services',
        kind: 'external-files-in-media-library/v1',
        baseURL: '/external-files-in-media-library/v1/services'
      }
    ]);
  }, []);
  const query = {
    search: !!search ? search : undefined,
    per_page: 10
  };
  let records = useSelect((select) => {
      return select('core').getEntityRecords('external-files-in-media-library/v1', 'services', query ) || [];
    }
  );

  /**
   * Create the commands.
   */
  const commands = useMemo(() => {
    return (records ?? []).slice(0, 10).map((record) => {
      return {
        name: record.value + " " + record.id,
        label: record.label,
        icon: connection,
        category: 'view',
        callback: ({ close }) => {
          const args = {
            page: 'efml_local_directories',
            method: record.value
          };
          document.location = addQueryArgs("upload.php", args);
          close();
        },
      };
    });
  }, [records, history]);

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
 * @returns {{commands: *, isLoading: *}}
 */
function useExternalSourcesInCommandPalette( { search } ) {
  useEffect(() => {
    dispatch('core').addEntities([
      {
        name: 'sources',
        kind: 'external-files-in-media-library/v1',
        baseURL: '/external-files-in-media-library/v1/sources'
      }
    ]);
  }, []);
  const query = {
    search: !!search ? search : undefined,
    per_page: 10
  };
  let records = useSelect((select) => {
      return select('core').getEntityRecords('external-files-in-media-library/v1', 'sources', query ) || [];
    }
  );

  /**
   * Create the commands.
   */
  const commands = useMemo(() => {
    return (records ?? []).slice(0, 10).map((record) => {
      return {
        name: record.value + " " + record.id,
        label: record.label,
        icon: connection,
        category: 'view',
        callback: ({ close }) => {
          const args = {
            page: 'efml_local_directories',
            method: record.method,
            term: record.value
          };
          document.location = addQueryArgs("upload.php", args);
          close();
        },
      };
    });
  }, [records, history]);

  return {
    commands
  };
}
