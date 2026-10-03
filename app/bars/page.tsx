import { getMongoClient } from '../../lib/mongodb';
import BarCardWithActions, { Bar } from '@/components/BarCardWithActions';

export const dynamic = 'force-dynamic';

async function getBars(): Promise<Bar[]> {
  const client = await getMongoClient();
  const db = client.db('playpoolnation');
  const bars = await db.collection('bars').find({}).toArray();

  return bars.map((bar): Bar => ({
    _id: bar._id.toString(),
    name: bar.name,
    address: bar.address,
    description: bar.description,
    tables: bar.tables,
    amenities: bar.amenities,
    imageUrl: bar.imageUrl,
  }));
}

export default async function BarsPage() {
  const bars = await getBars();

  return (
    <div className="max-w-6xl mx-auto p-6">
      <h1 className="text-3xl font-bold mb-6">All Bars with Pool Tables</h1>
      <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
        {bars.map((bar) => (
          <BarCardWithActions key={bar._id} bar={bar} />
        ))}
      </div>
    </div>
  );
}




