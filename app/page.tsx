import Header from '../components/Header';
import Footer from '../components/Footer';
import SearchBarComponent from '../components/SearchBarComponent';
import FeaturedBarsCarousel from '../components/FeaturedBarsCarousel';
import MapComponent from '../components/MapComponent';
import { getMongoClient } from '@/lib/mongodb';

export const dynamic = 'force-dynamic';

type Bar = {
  _id: string;
  name: string;
  address: string;
  description?: string;
  lat: number;
  lng: number;
  tables?: number;
  amenities?: string[];
  imageUrl?: string;
};

// Fetch bars from MongoDB
async function getBars(): Promise<Bar[]> {
  const client = await getMongoClient();
  const db = client.db('playpoolnation');
  const bars = await db.collection('bars').find({}).toArray();
  return bars as unknown as Bar[];
}

export default async function HomePage() {
  const bars = await getBars();

  return (
    <>
      <Header />

      <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <section className="text-center">
          <h1 className="text-4xl font-bold mb-2">Find Bars with Pool Tables Near You</h1>
          <p className="text-lg text-gray-600 mb-6">
            Explore nightlife, compare amenities, and rack &apos;em up at the best local bars.
          </p>
          <SearchBarComponent />
        </section>

        <section className="mt-12">
          <h2 className="text-2xl font-semibold mb-4">Top Rated Bars</h2>
          <FeaturedBarsCarousel bars={bars} />
        </section>

        <section className="mt-12">
          <h2 className="text-2xl font-semibold mb-4">Browse by Location</h2>
          <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
            {['New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix'].map((city) => (
              <button key={city} className="bg-gray-100 hover:bg-gray-200 p-4 rounded shadow">
                {city}
              </button>
            ))}
          </div>
        </section>

        <section className="mt-12">
          <h2 className="text-2xl font-semibold mb-4">Map of Local Pool Bars</h2>
          <MapComponent bars={bars} />
        </section>

        <section className="mt-12 bg-green-50 p-8 rounded-lg text-center">
          <h2 className="text-xl font-semibold mb-2">Know a Great Pool Bar?</h2>
          <p className="mb-4">
            Help fellow players discover new places. Submit your favorite bar today.
          </p>
          <a
            href="/submit"
            className="inline-block bg-green-600 text-white px-6 py-3 rounded hover:bg-green-700"
          >
            Submit a Bar
          </a>
        </section>
      </main>

      <Footer />
    </>
  );
}

