import { Country } from "@medusajs/medusa";
import Medusa from "@medusajs/medusa-js"
import useCountryOptions from "./hooks/use-country-options";
import { domainToASCII } from "url";
import { medusaClient } from "@lib/config"
import { useStore } from "./context/store-context";
import CountryMenu from "@modules/mobile-menu/components/country-menu";
import CountrySelect from "@modules/layout/components/country-select"
import { useRegions } from "medusa-react"
import { SearchRequest, SearchResponse } from "meilisearch";
import { instantMeiliSearch, InstantMeiliSearchInstance } from "@meilisearch/instant-meilisearch"

import Search from "@modules/common/icons/search";

export const searchClient: InstantMeiliSearchInstance = {
  search(requests: any) {
    let query = requests[0].params.query
    const json_region = localStorage.getItem("medusa_region")
    let region: any
    if (json_region) {
      region = JSON.parse(json_region) as { regionId: string; countryCode: string }
    }
    let languageCode: string
    switch (region.countryCode) {
      case 'en':
        languageCode = 'en'
        break;
      case 'se':
        languageCode = 'sv'
        break;
      case 'gb':
        languageCode = 'en'
        break;
      case 'de':
        languageCode = 'de'
        break;
      case 'dk':
        languageCode = 'da'
        break;
      case 'fr':
        languageCode = 'fr'
        break;
      case 'es':
        languageCode = 'es'
        break;
      case 'it':
        languageCode = 'it'
        break;
      default:
        languageCode = 'en'
    }

    const SEARCH_ENDPOINT='https://demo1-custom-search-api-eorit2dhbq-ew.a.run.app'
    const SEARCH_API_KEY='customsearch123' // Not best practice...
    const nProductsToRetrieve=20
    return fetch(`${process.env.NEXT_PUBLIC_MEDUSA_BACKEND_URL}/store/products/search`, {
      method: 'post',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(
        { 
          indexName: "",
          query: query,
          options: {
            languageCode: languageCode,
            nProductsToRetrieve: nProductsToRetrieve,
            apiKey: SEARCH_API_KEY,
            endpointURL: `${SEARCH_ENDPOINT}/search`
          }
        }
        ),
    }).then(async res => {
      let res_json = await res.json()
      return {
        results: [
          {
            hits: res_json.hits,
            nbHits: nProductsToRetrieve,
            page: 0,
            nbPages: 1,
            hitsPerPage: nProductsToRetrieve,
            processingTimeMS: 1,
            exhaustiveNbHits: true,
            query: query,
            params: ""
          }
        ]
      };
    });
  }
  ,
  searchForFacetValues: async function (_: any) { // Doesnt currently have any effect. Not sure if supported.
    // Probably need to have this method in the custom search endpoint
    // Should return SearchForFacetValuesResponse
    return await new Promise((resolve, reject) => {
      reject(
        new Error('SearchForFacetValues is not compatible with custom Meilisearch')
      )
      resolve([]) // added here to avoid compilation error
    })
  }
};

export const SEARCH_INDEX_NAME =
  process.env.NEXT_PUBLIC_INDEX_NAME || "products"

function mapCountryToLanguage(country: string) {
  let language: string
  switch (country) {
    case 'Sweden':
      language = 'Swedish'
      break;
    case 'United Kingdom':
      language = 'English'
      break;
    case 'Germany':
      language = 'German'
      break;
    case 'Denmark':
      language = 'Danish'
      break;
    case 'France':
      language = 'French'
      break;
    case 'Spain':
      language = 'Spanish'
      break;
    case 'Italy':
      language = 'Italian'
      break;
    default:
      language = 'English'
  }
  return language
}
export { mapCountryToLanguage }
